<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles data synchronization between Master Hub and Child Satellite sites.
 * Step 27: Client Syncer Module
 */
class Wordle_Sync {

	/**
	 * Main sync entry point. Called by Cron or Manual trigger.
	 */
	public static function sync() {
		$master_url = get_option( 'wordle_master_api_url' );
		$master_key = get_option( 'wordle_master_api_key' );
		$fallback_url = get_option( 'wordle_master_api_url_fallback' );

		if ( empty( $master_url ) || empty( $master_key ) ) {
			Wordle_Admin::log( 'Sync aborted: Primary Master URL or API Key is missing in settings.', 'error' );
			return false;
		}

		// Use Yesterday as start date to catch any missed updates/stats
		$start_date = date( 'Y-m-d', strtotime( '-1 day' ) );
		
		// Attempt Primary Sync
		$result = self::fetch_and_process( $master_url, $master_key, $start_date );

		// Fallback to secondary Hub if primary fails
		if ( is_wp_error( $result ) && ! empty( $fallback_url ) ) {
			Wordle_Admin::log( "Primary Hub failed (" . $result->get_error_message() . "). Attempting Fallback Hub...", 'warning' );
			$result = self::fetch_and_process( $fallback_url, $master_key, $start_date );
		}

		if ( is_wp_error( $result ) ) {
			Wordle_Admin::log( "Sync failed completely: " . $result->get_error_message(), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Fetches data from a specific API endpoint and processes it.
	 */
	private static function fetch_and_process( $url, $key, $start_date ) {
		$api_url = add_query_arg( 'start_date', $start_date, trailingslashit( $url ) . 'network-data' );

		$response = wp_remote_get( $api_url, array(
			'headers' => array(
				'X-Wordle-Key' => $key,
				'Accept'       => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new WP_Error( 'api_error', "Master Hub returned status code $status_code" );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['success'] ) || ! $data['success'] ) {
			return new WP_Error( 'invalid_data', "Received invalid JSON payload from Master Hub." );
		}

		$puzzles = $data['data'];
		$count = 0;

		foreach ( $puzzles as $puzzle_data ) {
			if ( self::process_puzzle( $puzzle_data ) ) {
				$count++;
			}
		}

		Wordle_Admin::log( "Sync successful: Mirrored $count puzzles from Master Hub.", 'success' );
		
		// Refresh local JSON cache
		Wordle_API::refresh_json_cache();

		return true;
	}

	/**
	 * Saves a single puzzle locally, ensuring UNIQUE hints are generated.
	 */
	private static function process_puzzle( $data ) {
		$date = $data['date'];
		$word = strtoupper( $data['word'] );

		// 1. Check if record exists locally
		$existing = Wordle_DB::get_puzzle_by_date( $date );
		
		// Prepare data for DB
		$puzzle_to_save = $data;
		$puzzle_to_save['entry_source'] = 'hub_sync';

		// 2. SEO SAFEGUARD: If local hints already exist, DO NOT overwrite them with Master hints.
		// This ensures every child site keeps its unique AI personas.
		if ( $existing && ! empty( $existing['hint1'] ) && strpos( $existing['hint1'], 'The word starts' ) === false ) {
			$puzzle_to_save['hint1'] = $existing['hint1'];
			$puzzle_to_save['hint2'] = $existing['hint2'];
			$puzzle_to_save['hint3'] = $existing['hint3'];
			$puzzle_to_save['final_hint'] = $existing['final_hint'];
		} else {
			// 3. Generate Local Unique Hints
			// Use the local AI configuration (Persona/Prompt) to differentiate from the Master Hub
			$hints = Wordle_AI::generate_hints( $word );
			
			if ( ! is_wp_error( $hints ) ) {
				$puzzle_to_save = array_merge( $puzzle_to_save, $hints );
			} else {
				// Fallback to pattern hints if AI fails
				$puzzle_to_save = array_merge( $puzzle_to_save, Wordle_Scraper::generate_fallback_hints( $word ) );
			}
		}

		return Wordle_DB::insert_puzzle( $puzzle_to_save );
	}
}
