<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Scheduler {

	public static function init() {
		add_action( 'wordle_daily_scrape_cron', array( __CLASS__, 'run_job' ) );
		
		if ( ! wp_next_scheduled( 'wordle_daily_scrape_cron' ) ) {
			self::schedule_next_run();
		}
	}

	public static function schedule_next_run() {
		$timezone = new DateTimeZone( get_option( 'wordle_hint_timezone', 'Asia/Karachi' ) );
		$now = new DateTime( 'now', $timezone );
		
		// Target 15:35 (03:35 PM)
		$target = new DateTime( 'now', $timezone );
		$target->setTime( 15, 35, 0 );
		
		if ( $now > $target ) {
			$target->modify( '+1 day' );
		}
		
		wp_schedule_single_event( $target->getTimestamp(), 'wordle_daily_scrape_cron' );
	}

	public static function run_job() {
		$attempt = get_transient( 'wordle_scrape_attempt' ) ?: 0;
		$max_attempts = 12;
		
		error_log( "Wordle Scraper: Starting job. Attempt " . ($attempt + 1) );

		// 1. Scrape for Today
		$today = current_time( 'Y-m-d' );
		$result_today = Wordle_Scraper::fetch_and_process( $today );

		// 2. Scrape for Tomorrow (Early release capture)
		$tomorrow = date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) );
		$result_tomorrow = Wordle_Scraper::fetch_and_process( $tomorrow );

		// We consider success if at least today was processed or already exists
		if ( ! is_wp_error( $result_today ) || $result_today->get_error_code() === 'db_error' ) {
			error_log( "Wordle Scraper: Today's processing finished (Success or Duplicate)." );
			delete_transient( 'wordle_scrape_attempt' );
			self::schedule_next_run(); // Schedule for next scheduled time
			
			$msg = "Today: " . ( is_wp_error( $result_today ) ? $result_today->get_error_message() : "Success" );
			$msg .= " | Tomorrow: " . ( is_wp_error( $result_tomorrow ) ? $result_tomorrow->get_error_message() : "Success" );
			
			return array( 'success' => true, 'message' => $msg );
		}

		// Fail logic for today's puzzle (Retry)
		$attempt++;
		if ( $attempt < $max_attempts ) {
			set_transient( 'wordle_scrape_attempt', $attempt, HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'wordle_daily_scrape_cron' );
			error_log( "Wordle Scraper: Today failed. Retrying in 5 mins. Error: " . $result_today->get_error_message() );
			return array( 'success' => false, 'message' => 'Today failed, retrying: ' . $result_today->get_error_message() );
		} else {
			delete_transient( 'wordle_scrape_attempt' );
			self::schedule_next_run(); 
			error_log( "Wordle Scraper: Max attempts reached for today." );
			return array( 'success' => false, 'message' => 'Max attempts reached' );
		}
	}
}

Wordle_Scheduler::init();
