<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'init', array( __CLASS__, 'maybe_refresh_cache' ) );
	}

	/**
	 * Automatically generates or refreshes the JSON cache if missing or expired.
	 * Triggered on 'init' hook.
	 */
	public static function maybe_refresh_cache() {
		$file_path = WORDLE_HINT_PATH . 'wordle-data.json';
		$regenerate = false;

		if ( ! file_exists( $file_path ) ) {
			$regenerate = true;
		} else {
			// Check if file is older than 24 hours
			$file_time = filemtime( $file_path );
			$one_day   = 24 * 60 * 60;
			if ( ( time() - $file_time ) > $one_day ) {
				$regenerate = true;
			}
		}

		if ( $regenerate ) {
			// Throttle to once every 15 minutes to prevent page load delays if scraping fails
			if ( false === get_transient( 'wordle_cache_refresh_lock' ) ) {
				set_transient( 'wordle_cache_refresh_lock', '1', 15 * MINUTE_IN_SECONDS );
				self::refresh_json_cache();
			}
		}
	}

	public static function register_routes() {
		register_rest_route( 'wordle/v1', '/data', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_wordle_data' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'wordle/v1', '/today', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_today_wordle' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'wordle/v1', '/solution', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_wordle_solution' ),
			'permission_callback' => array( __CLASS__, 'check_api_key' ),
		) );

		register_rest_route( 'wordle/v1', '/all', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_all_wordle' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'wordle/v1', '/save', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'save_wordle' ),
			'permission_callback' => array( __CLASS__, 'check_api_key' ),
		) );

		register_rest_route( 'wordle/v1', '/save-json', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'save_json_to_file' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'wordle/v1', '/refresh-json', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'handle_refresh_json' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	public static function check_api_key( $request ) {
		$key = $request->get_header( 'Authorization' );
		$stored_key = get_option( 'wordle_hint_api_key' );
		
		if ( ! $stored_key ) return true; // If not set, allow
		
		return ( 'Bearer ' . $stored_key === $key );
	}

	public static function get_wordle_data( $request ) {
		$locale = $request->get_param( 'locale' ) ?: 'global';
		$date   = $request->get_param( 'date' ) ?: current_time( 'Y-m-d' );
		
		$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );

		if ( ! $puzzle ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'No puzzle found for this date',
			), 404 );
		}
		
		return new WP_REST_Response( array(
			'success' => true,
			'data'    => $puzzle,
		), 200 );
	}

	public static function get_today_wordle( $request ) {
		$locale = $request->get_param( 'locale' ) ?: 'global';
		$date   = current_time( 'Y-m-d' );
		
		$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );

		if ( ! $puzzle ) {
			return new WP_REST_Response( array(
				'error' => 'No puzzle found for today',
			), 404 );
		}
		
		return new WP_REST_Response( array(
			'date'        => $puzzle['date'],
			'number'      => (int) $puzzle['puzzle_number'],
			'word'        => $puzzle['word'],
			'vowels'      => (int) $puzzle['vowel_count'],
			'starts_with' => $puzzle['first_letter'],
			'hints'       => array(
				'vague'    => $puzzle['hint1'],
				'category' => $puzzle['hint2'],
				'specific' => $puzzle['hint3'],
				'final'    => $puzzle['final_hint'],
			),
		), 200 );
	}

	public static function get_wordle_solution( $request ) {
		$locale = $request->get_param( 'locale' ) ?: 'global';
		$today = current_time( 'Y-m-d' );
		$puzzle = Wordle_DB::get_puzzle_by_date( $today, $locale );

		if ( ! $puzzle ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'No puzzle found' ), 404 );
		}

		return new WP_REST_Response( array(
			'success'       => true,
			'word'          => $puzzle['word'],
			'puzzle_number' => $puzzle['puzzle_number'],
			'date'          => $puzzle['date'],
		), 200 );
	}

	public static function get_all_wordle( $request ) {
		$page = $request->get_param( 'page' ) ?: 1;
		$limit = $request->get_param( 'limit' ) ?: 20;
		$locale = $request->get_param( 'locale' ) ?: 'global';
		
		global $wpdb;
		$table = Wordle_DB::get_table_name();
		$offset = ( $page - 1 ) * $limit;
		
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE locale = %s ORDER BY date DESC LIMIT %d OFFSET %d",
			$locale,
			$limit,
			$offset
		) );
		
		return new WP_REST_Response( array(
			'success' => true,
			'page'    => (int)$page,
			'data'    => $results,
		), 200 );
	}

	public static function save_wordle( $request ) {
		$params = $request->get_params();
		$inserted = Wordle_DB::insert_puzzle( $params );
		
		if ( $inserted ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Saved' ), 200 );
		}
		
		return new WP_REST_Response( array( 'success' => false, 'message' => 'Duplicate or error' ), 400 );
	}

	public static function save_json_to_file( $request ) {
		$data = $request->get_json_params();
		
		if ( empty( $data ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'No data received' ), 400 );
		}

		$file_path = WORDLE_HINT_PATH . 'wordle-data.json';
		$json_content = json_encode( $data, JSON_PRETTY_PRINT );

		if ( file_put_contents( $file_path, $json_content ) !== false ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'JSON updated successfully' ), 200 );
		} else {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Error saving JSON' ), 500 );
		}
	}

	public static function handle_refresh_json( $request ) {
		$count = self::refresh_json_cache();
		if ( $count !== false ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => "{$count}-day JSON cache generated successfully" ), 200 );
		} else {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Error generating JSON cache' ), 500 );
		}
	}

	public static function refresh_json_cache() {
		$locale = 'global';
		$tz_string = get_option( 'wordle_hint_timezone', 'Asia/Karachi' );
		$timezone = new DateTimeZone( $tz_string );
		$now = new DateTime( 'now', $timezone );
		
		$dates = array();
		
		// Cache 3 days in the past
		for ( $i = 3; $i >= 1; $i-- ) {
			$date = (clone $now)->modify("-$i days")->format('Y-m-d');
			$dates[] = $date;
		}
		
		// Cache today
		$dates[] = $now->format('Y-m-d');
		
		// Cache 7 days in the future
		for ( $i = 1; $i <= 7; $i++ ) {
			$date = (clone $now)->modify("+$i days")->format('Y-m-d');
			$dates[] = $date;
		}

		$cache_data = array();
		foreach ( $dates as $key => $date ) {
			$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );
			
			// If missing from DB, try to scrape it now
			if ( ! $puzzle ) {
				Wordle_Scraper::fetch_and_process( $date );
				$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );
			}

			if ( $puzzle ) {
				$cache_data[$date] = array(
					'date'        => $puzzle['date'],
					'number'      => (int) $puzzle['puzzle_number'],
					'word'        => $puzzle['word'],
					'vowels'      => (int) $puzzle['vowel_count'],
					'starts_with' => $puzzle['first_letter'],
					'hints'       => array(
						'vague'    => $puzzle['hint1'],
						'category' => $puzzle['hint2'],
						'specific' => $puzzle['hint3'],
						'final'    => $puzzle['final_hint'],
					),
				);
			}
		}

		if ( empty( $cache_data ) ) {
			return false;
		}

		$file_path = WORDLE_HINT_PATH . 'wordle-data.json';
		$success = file_put_contents( $file_path, json_encode( $cache_data, JSON_PRETTY_PRINT ) );
		
		return $success !== false ? count( $cache_data ) : false;
	}
}

Wordle_API::init();
