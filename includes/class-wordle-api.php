<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
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
}

Wordle_API::init();
