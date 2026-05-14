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
		$mode = get_option( 'wordle_operation_mode', 'master' );

		if ( $mode === 'client' ) {
			error_log( "Wordle Sync: Starting Client-Mode Sync from Master Hub." );
			$sync_result = Wordle_Sync::sync();
			
			if ( $sync_result ) {
				self::schedule_next_run();
				self::send_daily_reminders();
				return;
			} else {
				error_log( "Wordle Sync: Client-Mode Sync failed." );
				// We don't retry immediately to avoid slamming the Master Hub
				self::schedule_next_run(); 
				return;
			}
		}

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
			$msg = "Daily scrape job completed successfully. Handled: $success_count dates.";
			error_log( "Wordle Scraper: $msg" );
			if ( class_exists( 'Wordle_Admin' ) ) { Wordle_Admin::log( $msg, 'success' ); }
			delete_transient( 'wordle_scrape_attempt' );
			self::schedule_next_run();

			// TRIGGER DAILY REMINDERS
			self::send_daily_reminders();

			return array( 'success' => true, 'message' => $msg );
		}

		// Fail logic for today (Retry)
		$attempt++;
		if ( $attempt < $max_attempts ) {
			set_transient( 'wordle_scrape_attempt', $attempt, DAY_IN_SECONDS );
			// Retry in 30 minutes
			wp_schedule_single_event( time() + ( 30 * MINUTE_IN_SECONDS ), 'wordle_daily_scrape_cron' );
			$msg = "Daily scrape failed. Retrying in 30m (Attempt $attempt/$max_attempts). Error: $today_error";
			error_log( "Wordle Scraper: $msg" );
			if ( class_exists( 'Wordle_Admin' ) ) { Wordle_Admin::log( $msg, 'warning' ); }
			return array( 'success' => false, 'message' => $msg );
		} else {
			// FINAL FAILURE
			delete_transient( 'wordle_scrape_attempt' );
			self::schedule_next_run(); // Reset for tomorrow
			self::send_failure_alert( $today_error );
			$msg = "CRITICAL: Daily scraper failed after $max_attempts attempts. Admin notified. Final Error: $today_error";
			error_log( "Wordle Scraper: $msg" );
			if ( class_exists( 'Wordle_Admin' ) ) { Wordle_Admin::log( $msg, 'error' ); }
			return array( 'success' => false, 'message' => $msg );
		}
	}

	/**
	 * Send an email alert to the site administrator.
	 */
	public static function send_failure_alert( $error_message ) {
		$to = get_option( 'admin_email' );
		$subject = '⚠ Wordle Hint Pro: Scraper Failure Alert';
		$site_name = get_bloginfo( 'name' );
		
		$message = "Hello Admin,\n\n";
		$message .= "The automated Wordle scraper for {$site_name} has failed after multiple retry attempts.\n\n";
		$message .= "Last Error: {$error_message}\n";
		$message .= "Date: " . current_time( 'mysql' ) . "\n\n";
		$message .= "Please check your Wordle Hint settings and ensure the source URL is still active. You can also manually add the word in the plugin dashboard.\n\n";
		$message .= "-- Wordle Hint Pro Robot";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Send daily reminders to all active subscribers.
	 */
	public static function send_daily_reminders() {
		$today = current_time( 'Y-m-d' );
		$puzzle = Wordle_DB::get_puzzle_by_date( $today );

		if ( ! $puzzle ) return;

		// Prevent double-sending for the same day
		$last_sent = get_option( 'wordle_last_reminder_date' );
		if ( $last_sent === $today ) return;

		$subscribers = Wordle_DB::get_active_subscribers();
		if ( empty( $subscribers ) ) return;

		$subject = "Today's Wordle Hints: Puzzle #" . $puzzle['puzzle_number'];
		$site_name = get_bloginfo( 'name' );
		$site_url = home_url();

		foreach ( $subscribers as $sub ) {
			$email = $sub['email'];
			
			$message = "Hello Wordle fan!\n\n";
			$message .= "Here are your hints for today's Wordle (#{$puzzle['puzzle_number']}):\n\n";
			$message .= "1. " . $puzzle['hint1'] . "\n";
			$message .= "2. " . $puzzle['hint2'] . "\n";
			$message .= "3. " . $puzzle['hint3'] . "\n\n";
			$message .= "Need more clues or want to see the answer? Visit us here:\n";
			$message .= $site_url . "\n\n";
			$message .= "Good luck solving!\n\n";
			$message .= "-- The {$site_name} Team";

			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			wp_mail( $email, $subject, $message, $headers );
		}

		update_option( 'wordle_last_reminder_date', $today );
		error_log( "Wordle Scheduler: Sent daily reminders to " . count($subscribers) . " subscribers." );
	}
}

Wordle_Scheduler::init();
