<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Scraper {

	public static function fetch_and_process( $date = null ) {
		$base_url = get_option( 'wordle_hint_scrape_url' );
		if ( empty( $base_url ) ) {
			$base_url = 'https://www.nytimes.com/svc/wordle/v2/';
		}
		
		// Ensure trailing slash
		$base_url = trailingslashit( $base_url );
		
		// Use provided date or current date
		$date_string = $date ?: current_time( 'Y-m-d' );
		$url = $base_url . $date_string . '.json';

		// Jitter delay (2-5s)
		sleep( rand( 2, 5 ) );

		$response = wp_remote_get( $url, array(
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
			'timeout'    => 15,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( "Wordle Scraper Error: " . $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			error_log( "Wordle Scraper Error: Received status code " . $status_code );
			return new WP_Error( 'http_error', 'Received status code ' . $status_code );
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( empty( $json ) ) {
			return new WP_Error( 'json_error', 'Failed to parse JSON response' );
		}

		// Extract fields from NYT JSON v2
		$data = array(
			'word'          => strtoupper( $json['solution'] ?? '' ),
			'puzzle_number' => intval( $json['days_since_launch'] ?? 0 ),
			'date'          => $json['print_date'] ?? $date_string,
			'url'           => $url,
			'entry_source'  => 'automatic',
		);

		if ( empty( $data['word'] ) || empty( $data['puzzle_number'] ) ) {
			return new WP_Error( 'data_missing', 'Crucial Wordle data missing from JSON' );
		}

		// Analyze word locally
		$analysis = self::analyze_word( $data['word'] );
		$data = array_merge( $data, $analysis );

		// Generate hints via AI
		$hints = Wordle_AI::generate_hints( $data['word'] );
		if ( ! is_wp_error( $hints ) ) {
			$data = array_merge( $data, $hints );
		} else {
			// Fallback hints
			$data = array_merge( $data, self::generate_fallback_hints( $data['word'] ) );
		}

		// Save to DB
		$inserted = Wordle_DB::insert_puzzle( $data );
		
		if ( ! $inserted ) {
			error_log( "Wordle Scraper: Duplicate found or DB error, skipping " . $data['puzzle_number'] );
			return new WP_Error( 'db_error', 'Duplicate found, skipping' );
		}

		return $data;
	}

	public static function analyze_word( $word ) {
		$word = strtoupper( $word );
		$letters = str_split( $word );
		$vowels = array( 'A', 'E', 'I', 'O', 'U' );
		
		$v_count = 0;
		$c_count = 0;
		$counts = array_count_values( $letters );
		$repeated = array();

		foreach ( $counts as $char => $count ) {
			if ( $count > 1 ) {
				$repeated[] = $char;
			}
			if ( in_array( $char, $vowels ) ) {
				$v_count += $count;
			} else {
				$c_count += $count;
			}
		}

		return array(
			'vowel_count'      => $v_count,
			'consonant_count'  => $c_count,
			'repeated_letters' => implode( ',', $repeated ),
			'first_letter'     => substr( $word, 0, 1 ),
		);
	}

	public static function generate_fallback_hints( $word ) {
		$first = substr( $word, 0, 1 );
		$last = substr( $word, -1 );
		return array(
			'hint1'      => "The word starts with the letter $first.",
			'hint2'      => "It has " . strlen( $word ) . " letters.",
			'hint3'      => "The word ends with the letter $last.",
			'final_hint' => "It is a common 5-letter English word.",
		);
	}
}
