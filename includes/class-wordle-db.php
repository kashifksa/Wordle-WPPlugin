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
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			puzzle_number int(11) NOT NULL,
			word varchar(10) NOT NULL,
			hint1 text DEFAULT '' NOT NULL,
			hint2 text DEFAULT '' NOT NULL,
			hint3 text DEFAULT '' NOT NULL,
			final_hint text DEFAULT '' NOT NULL,
			vowel_count int(11) DEFAULT 0 NOT NULL,
			consonant_count int(11) DEFAULT 0 NOT NULL,
			first_letter varchar(1) DEFAULT '' NOT NULL,
			difficulty decimal(4,2) DEFAULT 0.00,
			average_guesses decimal(4,2) DEFAULT 0.00,
			guess_distribution text DEFAULT '',
			part_of_speech varchar(100) DEFAULT '',
			definition text DEFAULT '',
			etymology text DEFAULT '',
			pronunciation varchar(255) DEFAULT '',
			audio_url varchar(255) DEFAULT '',
			synonyms text DEFAULT '',
			antonyms text DEFAULT '',
			example_sentence text DEFAULT '',
			first_known_use varchar(100) DEFAULT '',
			definitions_json text DEFAULT '',
			entry_source varchar(50) DEFAULT 'scraper',
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

		// Migration: rename starts_with -> first_letter if old column exists
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM $table_name LIKE 'starts_with'" );
		if ( ! empty( $cols ) ) {
			$wpdb->query( "ALTER TABLE $table_name CHANGE starts_with first_letter varchar(1) DEFAULT '' NOT NULL" );
		}
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
			isset($data['puzzle_number']) ? $data['puzzle_number'] : 0,
			isset($data['date']) ? $data['date'] : ''
		), ARRAY_A );

		if ( $existing ) {
			return $wpdb->update( 
				$table_name, 
				$data, 
				array( 'id' => $existing['id'] ) 
			);
		}

		$result = $wpdb->insert( $table_name, $data );
		if ( $result === false ) {
			error_log( 'Wordle DB Error: ' . $wpdb->last_error );
			if ( class_exists( 'Wordle_Admin' ) ) {
				Wordle_Admin::log( 'DB Insert Error: ' . $wpdb->last_error, 'error' );
			}
		}
		return $result;
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
		$end_date = date( 'Y-m-t', strtotime( $start_date ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE date BETWEEN %s AND %s AND locale = %s ORDER BY date ASC",
			$start_date,
			$end_date,
			$locale
		), ARRAY_A );
	}

	/**
	 * Retrieves a paginated list of puzzles, excluding future ones.
	 */
	public static function get_paginated_puzzles( $page, $limit, $locale = 'global' ) {
		global $wpdb;
		$table_name = self::get_table_name();
		$offset = ( $page - 1 ) * $limit;
		$today = current_time( 'Y-m-d' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE locale = %s AND date <= %s ORDER BY date DESC LIMIT %d OFFSET %d",
			$locale,
			$today,
			$limit,
			$offset
		), ARRAY_A );
	}

	/**
	 * Returns the total count of non-future puzzles.
	 */
	public static function get_total_puzzles_count( $locale = 'global' ) {
		global $wpdb;
		$table_name = self::get_table_name();
		$today = current_time( 'Y-m-d' );

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE locale = %s AND date <= %s",
			$locale,
			$today
		) );
	}
	/**
	 * Returns all unique year/month combinations that have puzzles.
	 */
	public static function get_available_months( $locale = 'global' ) {
		global $wpdb;
		$table_name = self::get_table_name();
		$today = current_time( 'Y-m-d' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT YEAR(date) as year, MONTH(date) as month 
			 FROM $table_name 
			 WHERE locale = %s AND date <= %s 
			 ORDER BY year DESC, month DESC",
			$locale,
			$today
		), ARRAY_A );
	}
}
