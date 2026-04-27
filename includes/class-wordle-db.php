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
			hint1 text,
			hint2 text,
			hint3 text,
			final_hint text,
			vowel_count int(11),
			consonant_count int(11),
			repeated_letters varchar(32),
			first_letter varchar(1),
			url varchar(255),
			entry_source varchar(16) DEFAULT 'automatic',
			locale varchar(16) DEFAULT 'global',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY puzzle_number (puzzle_number),
			KEY date (date),
			KEY locale_date (locale, date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function insert_puzzle( $data ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		// Check if exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, hint1 FROM $table_name WHERE puzzle_number = %d",
			$data['puzzle_number']
		), ARRAY_A );

		if ( $existing ) {
			// If hints are missing, update them
			if ( empty( $existing['hint1'] ) && ! empty( $data['hint1'] ) ) {
				return $wpdb->update( 
					$table_name, 
					$data, 
					array( 'id' => $existing['id'] ) 
				);
			}
			return false; // Already exists and has hints
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
}
