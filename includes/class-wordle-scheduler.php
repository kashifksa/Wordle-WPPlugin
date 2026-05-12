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
		
		error_log( "Wordle Scraper: Starting daily job. Attempt " . ($attempt + 1) );

		$now_ts = current_time( 'timestamp' );
		
		// Dates to ensure we have data for: Yesterday, Today, and next 7 days
		$dates_to_check = array();
		$dates_to_check[] = date( 'Y-m-d', strtotime( '-1 day', $now_ts ) );
		$dates_to_check[] = date( 'Y-m-d', $now_ts );
		for ( $i = 1; $i <= 7; $i++ ) {
			$dates_to_check[] = date( 'Y-m-d', strtotime( "+$i days", $now_ts ) );
		}

		$today = date( 'Y-m-d', $now_ts );
		$success_count = 0;
		$skipped_count = 0;
		$error_occurred = false;
		$today_error = null;

		foreach ( $dates_to_check as $date ) {
			$existing = Wordle_DB::get_puzzle_by_date( $date );
			
			// Database-First: Skip ONLY if entry exists AND has stats
			if ( $existing ) {
				$has_stats = ! empty( $existing['average_guesses'] ) && ! empty( $existing['guess_distribution'] );
				if ( $has_stats ) {
					error_log( "Wordle Scraper: Skipped date $date: already exists with stats" );
					$skipped_count++;
					if ( $date === $today ) $success_count++; 
					continue;
				} else {
					error_log( "Wordle Scraper: Date $date exists but missing stats. Re-scraping for enrichment." );
				}
			}

			// Conditional Scraping: Scrape if missing
			error_log( "Wordle Scraper: Scraped date $date: missing entry" );
			$result = Wordle_Scraper::fetch_and_process( $date );

			if ( is_wp_error( $result ) ) {
				error_log( "Wordle Scraper: Error scraping $date: " . $result->get_error_message() );
				if ( $date === $today ) {
					$error_occurred = true;
					$today_error = $result->get_error_message();
				}
			} else {
				$success_count++;
				// Jitter delay for consecutive scrapes (5-8s)
				sleep( rand( 5, 8 ) );
			}
		}

		// Rebuild JSON cache after all processing
		Wordle_API::refresh_json_cache();

		// Handle Cron/Retry logic based on Today's success
		if ( ! $error_occurred ) {
			error_log( "Wordle Scraper: Job completed. Skipped: $skipped_count, New: " . ($success_count - $skipped_count) );
			delete_transient( 'wordle_scrape_attempt' );
			self::schedule_next_run();
			return array( 'success' => true, 'message' => "Job finished. Total handled: $success_count" );
		}

		// Fail logic for today (Retry)
		$attempt++;
		if ( $attempt < $max_attempts ) {
			set_transient( 'wordle_scrape_attempt', $attempt, HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'wordle_daily_scrape_cron' );
			error_log( "Wordle Scraper: Today's scrape failed. Retrying in 5 mins. Error: " . $today_error );
			return array( 'success' => false, 'message' => 'Today failed, retrying: ' . $today_error );
		} else {
			delete_transient( 'wordle_scrape_attempt' );
			self::schedule_next_run(); 
			error_log( "Wordle Scraper: Max attempts reached for today." );
			return array( 'success' => false, 'message' => 'Max attempts reached' );
		}
	}
}

Wordle_Scheduler::init();
