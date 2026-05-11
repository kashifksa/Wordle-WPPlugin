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
			difficulty decimal(3,1),
			average_guesses decimal(3,1),
			guess_distribution text,
			part_of_speech varchar(32),
			definition text,
			pronunciation varchar(128),
			audio_url varchar(255),
			etymology text,
			first_known_use varchar(32),
			synonyms text,
			antonyms text,
			example_sentence text,
			num_definitions tinyint(4),
			definitions_json text,
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
