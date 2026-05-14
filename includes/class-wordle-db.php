<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_DB {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wordle_data';
	}

	public static function create_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wordle_data';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			puzzle_number int(11) NOT NULL,
			word varchar(10) NOT NULL,
			hint1 text NOT NULL,
			hint2 text NOT NULL,
			hint3 text NOT NULL,
			final_hint text NOT NULL,
			vowel_count int(11) NOT NULL,
			consonant_count int(11) NOT NULL,
			starts_with varchar(1) NOT NULL,
			difficulty decimal(4,2),
			average_guesses decimal(4,2),
			guess_distribution text,
			part_of_speech varchar(100),
			definition text,
			etymology text,
			pronunciation varchar(255),
			audio_url varchar(255),
			synonyms text,
			antonyms text,
			example_sentence text,
			first_known_use varchar(100),
			definitions_json text,
			locale varchar(20) DEFAULT 'global',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY puzzle_number (puzzle_number)
		) $charset_collate;";

		// Subscribers Table
		$sub_table = $wpdb->prefix . 'wordle_subscribers';
		$sql_sub = "CREATE TABLE $sub_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			email varchar(100) NOT NULL,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		dbDelta( $sql_sub );
	}

	/**
	 * Adds a new subscriber.
	 */
	public static function add_subscriber( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wordle_subscribers';
		
		return $wpdb->insert(
			$table,
			array(
				'email'      => sanitize_email( $email ),
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Retrieves all active subscribers.
	 */
	public static function get_active_subscribers() {
		global $wpdb;
		$table = $wpdb->prefix . 'wordle_subscribers';
		return $wpdb->get_results( "SELECT email FROM $table WHERE status = 'active'", ARRAY_A );
	}

	public static function insert_puzzle( $data ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		// Check if exists by puzzle_number
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE puzzle_number = %d OR date = %s",
			$data['puzzle_number'],
			$data['date']
		), ARRAY_A );

		if ( $existing ) {
			return $wpdb->update( 
				$table_name, 
				$data, 
				array( 'id' => $existing['id'] ) 
			);
		}

		return $wpdb->insert( $table_name, $data );
	}

	public static function get_latest_puzzles( $limit = 3, $locale = 'global' ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE locale = %s ORDER BY date DESC LIMIT %d",
			$locale,
			$limit
		), ARRAY_A );
	}

	public static function get_puzzle_by_date( $date, $locale = 'global' ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE date = %s AND locale = %s",
			$date,
			$locale
		), ARRAY_A );
	}

	public static function get_puzzles_for_month( $month, $year, $locale = 'global' ) {
		global $wpdb;
		$table_name = self::get_table_name();
		$start_date = "{$year}-{$month}-01";
		$end_date   = date( 'Y-m-t', strtotime( $start_date ) );
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE locale = %s AND date >= %s AND date <= %s ORDER BY date ASC",
			$locale,
			$start_date,
			$end_date
		), ARRAY_A );
	}

	public static function get_paginated_puzzles( $page = 1, $limit = 24, $locale = 'global', $max_date = null ) {
		global $wpdb;
		$table_name = self::get_table_name();
		$offset = ( $page - 1 ) * $limit;
		
		$query = "SELECT * FROM $table_name WHERE locale = %s";
		$params = array( $locale );

		if ( $max_date ) {
			$query .= " AND date <= %s";
			$params[] = $max_date;
		}

		$query .= " ORDER BY date DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );
	}

	public static function get_total_puzzles_count( $locale = 'global', $max_date = null ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		$query = "SELECT COUNT(*) FROM $table_name WHERE locale = %s";
		$params = array( $locale );

		if ( $max_date ) {
			$query .= " AND date <= %s";
			$params[] = $max_date;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $query, $params ) );
	}
}
