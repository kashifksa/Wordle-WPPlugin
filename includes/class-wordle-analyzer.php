<?php
/**
 * Analytical engine for Wordle Hint Pro.
 * Calculates letter frequencies and positional data.
 */

class Wordle_Analyzer {

	/**
	 * Get positional frequency for a specific letter and position.
	 */
	public static function get_positional_stats() {
		$stats = get_transient( 'wh_positional_stats' );
		if ( false !== $stats ) {
			return $stats;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wordle_data';
		$words = $wpdb->get_col( "SELECT word FROM $table_name" );

		if ( empty( $words ) ) {
			return array();
		}

		$total_words = count( $words );
		$positional_counts = array_fill( 0, 5, array() );

		foreach ( $words as $word ) {
			$chars = str_split( strtoupper( $word ) );
			foreach ( $chars as $pos => $char ) {
				if ( $pos < 5 ) {
					if ( ! isset( $positional_counts[$pos][$char] ) ) {
						$positional_counts[$pos][$char] = 0;
					}
					$positional_counts[$pos][$char]++;
				}
			}
		}

		// Convert to percentages
		$stats = array();
		foreach ( $positional_counts as $pos => $chars ) {
			arsort( $chars );
			$stats[$pos] = array();
			foreach ( $chars as $char => $count ) {
				$stats[$pos][$char] = round( ( $count / $total_words ) * 100, 1 );
			}
		}

		set_transient( 'wh_positional_stats', $stats, DAY_IN_SECONDS );
		return $stats;
	}

	/**
	 * Generate a random strategy insight based on the current word.
	 */
	public static function get_insight_for_word( $word ) {
		$stats = self::get_positional_stats();
		if ( empty( $stats ) ) {
			return '';
		}

		$chars = str_split( strtoupper( $word ) );
		$insights = array();

		foreach ( $chars as $pos => $char ) {
			if ( isset( $stats[$pos][$char] ) ) {
				$pct = $stats[$pos][$char];
				$rank = array_search( $char, array_keys( $stats[$pos] ) ) + 1;
				
				if ( $rank === 1 ) {
					$insights[] = "Strategy: The letter <strong>$char</strong> is the #1 most common letter for position " . ( $pos + 1 ) . "!";
				} elseif ( $rank <= 3 ) {
					$insights[] = "Data Insight: <strong>$char</strong> is one of the top 3 letters usually found in position " . ( $pos + 1 ) . ".";
				} elseif ( $pct > 10 ) {
					$insights[] = "Pro Tip: <strong>$char</strong> appears in position " . ( $pos + 1 ) . " in about $pct% of all Wordle words.";
				}
			}
		}

		// General insights if no specific positional matches are "exciting"
		if ( count( $insights ) < 2 ) {
			$vowels = preg_match_all( '/[AEIOU]/', strtoupper( $word ) );
			if ( $vowels >= 3 ) {
				$insights[] = "Expert View: Today's word is vowel-heavy, making it a great day to start with 'ADIEU' or 'AUDIO'.";
			}
		}

		if ( empty( $insights ) ) {
			return "Strategy: Today's word uses a unique letter combination. Check the frequency charts below!";
		}

		return $insights[ array_rand( $insights ) ];
	}
}
