<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		if ( ! is_admin() ) {
			add_action( 'init', array( __CLASS__, 'maybe_refresh_cache' ) );
		}
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
			'permission_callback' => array( __CLASS__, 'public_permission_check' ),
		) );

		// /today is now deprecated in favor of /data (which defaults to today)
		// Pointing it to get_wordle_data for backward compatibility.
		register_rest_route( 'wordle/v1', '/today', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_wordle_data' ),
			'permission_callback' => array( __CLASS__, 'public_permission_check' ),
		) );

		register_rest_route( 'wordle/v1', '/solution', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_wordle_solution' ),
			'permission_callback' => array( __CLASS__, 'check_api_key' ),
		) );

		register_rest_route( 'wordle/v1', '/all', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_all_wordle' ),
			'permission_callback' => array( __CLASS__, 'public_permission_check' ),
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
		
		register_rest_route( 'wordle/v1', '/share-image/(?P<date>\d{4}-\d{2}-\d{2})', array(
			'methods'  => 'GET',
			'callback' => array( 'Wordle_Image_Generator', 'serve_image' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'wordle/v1', '/subscribe', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'subscribe_email' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'wordle/v1', '/network-data', array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_network_data' ),
			'permission_callback' => '__return_true', // Validation happens inside callback
		) );
	}

	/**
	 * Securely shares raw puzzle data with authorized satellite sites.
	 * Step 26: Master Hub API
	 */
	public static function get_network_data( $request ) {
		$master_key = get_option( 'wordle_network_sharing_key' );
		$provided_key = $request->get_header( 'X-Wordle-Key' );

		// 1. Security Check: API Key is required
		if ( empty( $master_key ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Network sharing is not configured on Master Hub.' ), 500 );
		}

		if ( $provided_key !== $master_key ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Unauthorized: Invalid API Key.' ), 401 );
		}

		// 2. Fetch Data (Single Date or Bulk Sync)
		$start_date = $request->get_param( 'start_date' );
		$date = $request->get_param( 'date' );
		
		global $wpdb;
		$table = Wordle_DB::get_table_name();
		$results = array();

		if ( $start_date ) {
			// Bulk Sync Mode (Fetch all puzzles from start_date onwards)
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE date >= %s ORDER BY date ASC LIMIT 30",
				$start_date
			), ARRAY_A );
			if ( $rows ) {
				foreach ( $rows as $row ) {
					$results[] = self::sanitize_puzzle_for_api( $row );
				}
			}
		} else {
			// Single Date Mode
			if ( ! $date ) $date = current_time( 'Y-m-d' );
			$puzzle = Wordle_DB::get_puzzle_by_date( $date );
			if ( $puzzle ) {
				$results = self::sanitize_puzzle_for_api( $puzzle );
			}
		}

		if ( empty( $results ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => "No puzzle data found." ), 404 );
		}

		// 3. Return Clean Raw Data
		$response_data = array(
			'success' => true,
			'data'    => $results
		);

		$response = new WP_REST_Response( $response_data, 200 );
		
		// 4. CORS Support
		$response->header( 'Access-Control-Allow-Origin', '*' );
		$response->header( 'Access-Control-Allow-Methods', 'GET' );
		$response->header( 'Access-Control-Allow-Headers', 'X-Wordle-Key' );

		return $response;
	}

	/**
	 * Helper to remove internal DB fields from API response
	 */
	private static function sanitize_puzzle_for_api( $puzzle ) {
		return array(
			'word'               => $puzzle['word'],
			'puzzle_number'      => $puzzle['puzzle_number'],
			'date'               => $puzzle['date'],
			'vowel_count'        => $puzzle['vowel_count'],
			'consonant_count'    => $puzzle['consonant_count'],
			'starts_with'        => $puzzle['starts_with'],
			'difficulty'         => $puzzle['difficulty'],
			'average_guesses'    => $puzzle['average_guesses'],
			'guess_distribution' => $puzzle['guess_distribution'],
			'part_of_speech'     => $puzzle['part_of_speech'],
			'definition'         => $puzzle['definition'],
			'etymology'          => $puzzle['etymology'],
			'pronunciation'      => $puzzle['pronunciation'],
			'audio_url'          => $puzzle['audio_url'],
			'synonyms'           => $puzzle['synonyms'],
			'antonyms'           => $puzzle['antonyms'],
			'example_sentence'   => $puzzle['example_sentence'],
			'definitions_json'   => $puzzle['definitions_json'],
		);
	}

	/**
	 * Handles a new email subscription via REST API.
	 */
	public static function subscribe_email( $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( ! is_email( $email ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Please provide a valid email address.',
			), 400 );
		}

		// Check if already subscribed
		global $wpdb;
		$table = $wpdb->prefix . 'wordle_subscribers';
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE email = %s", $email ) );

		if ( $exists ) {
			return new WP_REST_Response( array(
				'success' => true,
				'message' => 'You are already on our list! Get ready for daily hints.',
			), 200 );
		}

		$added = Wordle_DB::add_subscriber( $email );

		if ( $added ) {
			return new WP_REST_Response( array(
				'success' => true,
				'message' => 'Welcome! You will now receive daily Wordle hints.',
			), 200 );
		}

		return new WP_REST_Response( array(
			'success' => false,
			'message' => 'Subscription failed. Please try again later.',
		), 500 );
	}

	public static function check_api_key( $request ) {
		$key = $request->get_header( 'Authorization' );
		$stored_key = get_option( 'wordle_hint_api_key' );
		
		// If no key is set, fail secure (return false) instead of fail open.
		if ( ! $stored_key ) {
			return false;
		}
		
		return ( 'Bearer ' . $stored_key === $key );
	}

	/**
	 * Combined check for public endpoints (Rate Limiting)
	 */
	public static function public_permission_check( $request ) {
		return self::check_rate_limit( $request );
	}

	/**
	 * IP-based rate limiting using transients
	 */
	public static function check_rate_limit( $request ) {
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		if ( $ip === 'unknown' ) return true;

		$transient_key = 'wordle_ratelimit_' . md5( $ip );
		$hits = (int) get_transient( $transient_key );

		$limit = 60; // 60 requests
		$window = 60; // per 60 seconds

		if ( $hits >= $limit ) {
			return new WP_Error( 'rest_rate_limited', 'Too many requests. Please slow down.', array( 'status' => 429 ) );
		}

		set_transient( $transient_key, $hits + 1, $window );
		return true;
	}

	public static function get_wordle_data( $request ) {
		$locale = $request->get_param( 'locale' ) ?: 'global';
		$date   = $request->get_param( 'date' ) ?: current_time( 'Y-m-d' );
		
		$cache_key = "wordle_data_{$date}_{$locale}";
		$cached = wp_cache_get( $cache_key, 'wordle_hint' );
		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );

		if ( ! $puzzle ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'No puzzle found for this date',
			), 404 );
		}
		
		$response_data = self::prepare_puzzle_response( $puzzle );
		wp_cache_set( $cache_key, $response_data, 'wordle_hint', HOUR_IN_SECONDS );

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Formats raw DB record into a standardized API response structure.
	 */
	private static function prepare_puzzle_response( $puzzle ) {
		return array(
			'success'     => true,
			'date'        => $puzzle['date'],
			'number'      => (int) $puzzle['puzzle_number'],
			'word'        => $puzzle['word'],
			'vowels'      => (int) $puzzle['vowel_count'],
			'starts_with' => $puzzle['first_letter'] ?? substr($puzzle['word'], 0, 1),
			'hints'       => array(
				'vague'    => $puzzle['hint1'],
				'category' => $puzzle['hint2'],
				'specific' => $puzzle['hint3'],
				'final'    => $puzzle['final_hint'],
			),
			'stats'       => array(
				'difficulty'      => floatval( $puzzle['difficulty'] ?? 0 ),
				'average_guesses' => floatval( $puzzle['average_guesses'] ?? 0 ),
				'distribution'    => json_decode( $puzzle['guess_distribution'] ?? '[]' ),
			),
			'dictionary'  => array(
				'part_of_speech'   => $puzzle['part_of_speech'] ?? '',
				'definition'       => $puzzle['definition'] ?? '',
				'pronunciation'    => $puzzle['pronunciation'] ?? '',
				'audio_url'        => $puzzle['audio_url'] ?? '',
				'etymology'        => $puzzle['etymology'] ?? '',
				'example_sentence' => $puzzle['example_sentence'] ?? '',
				'first_known_use'  => $puzzle['first_known_use'] ?? '',
				'synonyms'         => $puzzle['synonyms'] ?? '[]',
				'antonyms'         => $puzzle['antonyms'] ?? '[]',
			),
		);
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
		$page  = $request->get_param( 'page' ) ?: 1;
		$limit = $request->get_param( 'limit' ) ?: 20;
		$limit = min( 100, intval( $limit ) );
		$locale = $request->get_param( 'locale' ) ?: 'global';
		
		$cache_key = "wordle_all_{$locale}_p{$page}_l{$limit}";
		$cached = wp_cache_get( $cache_key, 'wordle_hint' );
		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		global $wpdb;
		$table = Wordle_DB::get_table_name();
		$offset = ( $page - 1 ) * $limit;
		
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE locale = %s ORDER BY date DESC LIMIT %d OFFSET %d",
			$locale, $limit, $offset
		), ARRAY_A );
		
		$response_data = array(
			'success' => true,
			'page'    => (int)$page,
			'data'    => $results,
		);
		
		wp_cache_set( $cache_key, $response_data, 'wordle_hint', HOUR_IN_SECONDS );

		return new WP_REST_Response( $response_data, 200 );
	}

	public static function save_wordle( $request ) {
		$params = $request->get_params();
		$sanitized_data = array();
		
		$whitelist = array(
			'date'               => 'sanitize_text_field',
			'puzzle_number'      => 'intval',
			'word'               => 'sanitize_text_field',
			'hint1'              => 'sanitize_textarea_field',
			'hint2'              => 'sanitize_textarea_field',
			'hint3'              => 'sanitize_textarea_field',
			'final_hint'         => 'sanitize_textarea_field',
			'vowel_count'        => 'intval',
			'consonant_count'    => 'intval',
			'first_letter'       => 'sanitize_text_field',
			'difficulty'         => 'floatval',
			'average_guesses'    => 'floatval',
			'guess_distribution' => 'sanitize_text_field',
			'part_of_speech'     => 'sanitize_text_field',
			'definition'         => 'sanitize_textarea_field',
			'pronunciation'      => 'sanitize_text_field',
			'audio_url'          => 'esc_url_raw',
			'etymology'          => 'sanitize_textarea_field',
			'example_sentence'   => 'sanitize_textarea_field',
			'first_known_use'    => 'sanitize_text_field',
			'synonyms'           => 'sanitize_text_field',
			'antonyms'           => 'sanitize_text_field',
			'locale'             => 'sanitize_text_field',
		);

		foreach ( $whitelist as $field => $sanitize_callback ) {
			if ( isset( $params[$field] ) ) {
				$sanitized_data[$field] = call_user_func( $sanitize_callback, $params[$field] );
			}
		}

		if ( empty( $sanitized_data['puzzle_number'] ) || empty( $sanitized_data['date'] ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Missing required fields: puzzle_number and date' ), 400 );
		}

		$inserted = Wordle_DB::insert_puzzle( $sanitized_data );
		
		if ( $inserted ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Saved' ), 200 );
		}
		
		return new WP_REST_Response( array( 'success' => false, 'message' => 'Database error or duplicate' ), 400 );
	}

	public static function save_json_to_file( $request ) {
		$data = $request->get_json_params();
		
		if ( empty( $data ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'No data received' ), 400 );
		}

		$file_path = WORDLE_HINT_PATH . 'wordle-data.json';
		$json_content = json_encode( $data );

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
		if ( empty( $tz_string ) ) {
			$tz_string = 'Asia/Karachi';
		}
		$timezone = new DateTimeZone( $tz_string );
		$now = new DateTime( 'now', $timezone );
		
		$dates = array();
		
		// Yesterday
		$dates[] = (clone $now)->modify("-1 day")->format('Y-m-d');
		
		// Today
		$dates[] = $now->format('Y-m-d');
		
		// Next 7 days
		for ( $i = 1; $i <= 7; $i++ ) {
			$date = (clone $now)->modify("+$i days")->format('Y-m-d');
			$dates[] = $date;
		}

		$puzzles_data = array();
		foreach ( $dates as $date ) {
			$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );
			
			// If missing from DB, try to get it
			if ( ! $puzzle ) {
				$mode = get_option( 'wordle_operation_mode', 'master' );
				if ( $mode === 'client' ) {
					// In Client mode, we don't scrape; we just log that it's missing.
					// Sync cron will handle it, or user can manual sync.
				} else {
					Wordle_Scraper::fetch_and_process( $date );
				}
				$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );
			}

			if ( $puzzle ) {
				$puzzles_data[$date] = array(
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
					'stats'       => array(
						'difficulty'      => floatval( $puzzle['difficulty'] ?? 0 ),
						'average_guesses' => floatval( $puzzle['average_guesses'] ?? 0 ),
						'distribution'    => json_decode( $puzzle['guess_distribution'] ?? '[]' ),
					),
					'dictionary'  => array(
						'part_of_speech'   => $puzzle['part_of_speech'] ?? '',
						'definition'       => $puzzle['definition'] ?? '',
						'pronunciation'    => $puzzle['pronunciation'] ?? '',
						'audio_url'        => $puzzle['audio_url'] ?? '',
						'etymology'        => $puzzle['etymology'] ?? '',
						'example_sentence' => $puzzle['example_sentence'] ?? '',
						'first_known_use'  => $puzzle['first_known_use'] ?? '',
						'synonyms'         => $puzzle['synonyms'] ?? '[]',
						'antonyms'         => $puzzle['antonyms'] ?? '[]',
					),
				);
			}
		}

		if ( empty( $puzzles_data ) ) {
			return false;
		}

		// Standardized structure for future Client plugins
		$final_output = array(
			'meta' => array(
				'last_updated' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'plugin'       => 'Wordle Hint Pro',
				'version'      => WORDLE_HINT_VERSION
			),
			'data' => $puzzles_data
		);

		$file_path = WORDLE_HINT_PATH . 'wordle-data.json';
		$success = file_put_contents( $file_path, json_encode( $final_output ) );
		
		// Refresh Wordle Solver JSON as well
		if ( class_exists( 'Wordle_Solver' ) ) {
			Wordle_Solver::generate_solver_json();
		}

		if ( $success !== false && function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'wordle_all_puzzles', 'wordle_hint' );
		}

		return $success !== false ? count( $puzzles_data ) : false;
	}
}

Wordle_API::init();
