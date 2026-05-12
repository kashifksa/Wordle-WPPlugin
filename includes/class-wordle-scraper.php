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

		// Check if puzzle already exists in DB
		$existing = Wordle_DB::get_puzzle_by_date( $data['date'] );
		
		if ( $existing && ! empty( $existing['average_guesses'] ) && ! empty( $existing['guess_distribution'] ) ) {
			return $existing; // Already has stats, no need to update
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

		// Try to fetch WordleBot stats for this puzzle
		$stats = self::fetch_wordlebot_stats( $data['puzzle_number'] );
		if ( ! is_wp_error( $stats ) && $stats ) {
			$data = array_merge( $data, $stats );
		}

		// Enrichment: Merriam-Webster Dictionary
		$dictionary = Wordle_Dictionary::fetch_enrichment( $data['word'] );
		if ( ! is_wp_error( $dictionary ) ) {
			$data = array_merge( $data, $dictionary );
		}

		// Save to DB (Insert or Update)
		$inserted = Wordle_DB::insert_puzzle( $data );
		
		if ( ! $inserted ) {
			error_log( "Wordle Scraper: DB error, failed to save " . $data['puzzle_number'] );
			return new WP_Error( 'db_error', 'Failed to save to DB' );
		}

		// Trigger JSON cache refresh to make data live
		if ( class_exists( 'Wordle_API' ) ) {
			Wordle_API::refresh_json_cache();
		}

		return $data;
	}

	/**
	 * Fetch WordleBot statistics from Engaging Data's JS archive.
	 */
	public static function fetch_wordlebot_stats( $puzzle_number ) {
		$local_file = WORDLE_HINT_PATH . 'engagingData.js';
		$body = '';
		$is_old = false;

		if ( file_exists( $local_file ) ) {
			// Get dynamic interval from settings (default 4 hours)
			$interval_hours = intval( get_option( 'wordle_stats_refresh_interval', 4 ) );
			if ( ( time() - filemtime( $local_file ) ) > ( $interval_hours * HOUR_IN_SECONDS ) ) {
				$is_old = true;
			}
			$body = file_get_contents( $local_file );
		}

		// Fetch from remote if local missing, old, or doesn't contain the puzzle
		if ( empty( $body ) || $is_old || strpos( $body, '"' . $puzzle_number . '":' ) === false ) {
			$url = 'https://engaging-data.com/pages/scripts/wordlebot/wordlepuzzles.js';
			$response = wp_remote_get( $url, array( 
				'timeout'    => 20,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36'
			) );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$body = wp_remote_retrieve_body( $response );
				if ( ! empty( $body ) ) {
					file_put_contents( $local_file, $body ); // Update local cache
				}
			} elseif ( empty( $body ) ) {
				// If remote failed and we have no local body at all
				return is_wp_error( $response ) ? $response : new WP_Error( 'http_error', 'Failed to fetch stats' );
			}
		}

		if ( empty( $body ) ) {
			return new WP_Error( 'empty_response', 'Failed to fetch WordleBot stats' );
		}

		// Extract JSON from JS variable: wordlepuzzles = { ... }
		$start = strpos( $body, '{' );
		if ( $start === false ) {
			return new WP_Error( 'parse_error', 'Could not find JSON start in stats file' );
		}

		$json_str = substr( $body, $start );
		$json_str = rtrim( $json_str, "; \n\r" );
		
		$all_stats = json_decode( $json_str, true );
		if ( empty( $all_stats ) ) {
			error_log( "Wordle Scraper: JSON decode failed for stats. Error: " . json_last_error_msg() );
			return new WP_Error( 'json_error', 'Failed to parse stats JSON' );
		}

		if ( isset( $all_stats[$puzzle_number] ) ) {
			$p_stats = $all_stats[$puzzle_number];
			
			// Engaging Data distribution is in 'individual' array: [p1, p2, p3, p4, p5, p6]
			// These are percentages. The remainder is "No Solve" (7 tries).
			$dist = $p_stats['individual'] ?? array();
			
			if ( ! empty( $dist ) ) {
				// Calculate Average Guesses
				$sum_pct = 0;
				$weighted_sum = 0;
				
				foreach ( $dist as $i => $pct ) {
					$guesses = $i + 1;
					$weighted_sum += ( $guesses * $pct );
					$sum_pct += $pct;
				}
				
				// Handle "No Solve" (assumed to be 7 guesses)
				$no_solve_pct = 100 - $sum_pct;
				$weighted_sum += ( 7 * $no_solve_pct );
				
				$avg = $weighted_sum / 100;
				
				// Calculate a Difficulty Rating (1-5)
				// Typical Wordle averages: 3.4 (Very Easy) to 4.8 (Very Hard)
				$difficulty = 1.0;
				if ( $avg > 0 ) {
					// Map 3.5 -> 1.0, 4.7 -> 5.0
					$difficulty = ( ( $avg - 3.5 ) / ( 4.7 - 3.5 ) ) * 4 + 1;
					$difficulty = max( 1.0, min( 5.0, round( $difficulty, 1 ) ) );
				}

				return array(
					'difficulty'         => $difficulty,
					'average_guesses'    => round( $avg, 2 ),
					'guess_distribution' => json_encode( $dist ),
					'url'                => 'https://engaging-data.com/wordle-guess-distribution/?p=' . $puzzle_number
				);
			}
		}

		return false;
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
		// Try Advanced Static Fallback first
		if ( class_exists( 'Wordle_Static_Hints' ) ) {
			$static = Wordle_Static_Hints::get_hints( $word );
			if ( $static ) {
				return $static;
			}
		}

		// Tertiary Fallback: Pattern-based
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
