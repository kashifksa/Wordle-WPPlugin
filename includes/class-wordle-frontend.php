<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Frontend {

	/**
	 * Stores the active puzzle for SEO hooks.
	 * Populated when the shortcode renders, then used by wp_head/title filters.
	 */
	private static $seo_puzzle = null;

	/**
	 * Register SEO hooks. Called on init.
	 */
	public static function init_seo_hooks() {
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_seo_title' ), 20 );
		add_action( 'wp_head',              array( __CLASS__, 'output_seo_meta' ), 5 );

		// Rank Math compatibility
		add_filter( 'rank_math/frontend/title',       array( __CLASS__, 'filter_rankmath_title' ), 20 );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'filter_rankmath_desc' ), 20 );

		// Yoast SEO compatibility
		add_filter( 'wpseo_title',    array( __CLASS__, 'filter_yoast_title' ), 20 );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'filter_yoast_desc' ), 20 );
	}

	// -------------------------------------------------------------------------
	// SEO HELPERS
	// -------------------------------------------------------------------------

	/**
	 * Returns a formatted puzzle date string, e.g. "May 1, 2026".
	 */
	private static function get_formatted_date( $puzzle ) {
		return date( 'F j, Y', strtotime( $puzzle['date'] ) );
	}

	/**
	 * Builds the dynamic SEO title string.
	 * Format: "Wordle Hint #1777 - May 1, 2026"
	 */
	private static function build_seo_title() {
		if ( ! self::$seo_puzzle || self::$seo_puzzle['puzzle_number'] === '---' ) {
			return null;
		}
		return sprintf(
			'Wordle Hint #%s - %s',
			esc_html( self::$seo_puzzle['puzzle_number'] ),
			self::get_formatted_date( self::$seo_puzzle )
		);
	}

	/**
	 * Builds the dynamic meta description string.
	 * Keyword-rich: includes puzzle number, date, starts_with, vowel count.
	 */
	private static function build_seo_description() {
		if ( ! self::$seo_puzzle || self::$seo_puzzle['puzzle_number'] === '---' ) {
			return null;
		}
		return sprintf(
			'Wordle %s answer and hints for %s. The word starts with "%s" and has %s vowel(s). Unlock progressive clues without spoilers and solve today\'s Wordle!',
			esc_html( self::$seo_puzzle['puzzle_number'] ),
			self::get_formatted_date( self::$seo_puzzle ),
			esc_html( self::$seo_puzzle['first_letter'] ),
			esc_html( self::$seo_puzzle['vowel_count'] )
		);
	}

	// -------------------------------------------------------------------------
	// SEO FILTERS
	// -------------------------------------------------------------------------

	/** WordPress core title filter */
	public static function filter_seo_title( $title_parts ) {
		$new_title = self::build_seo_title();
		if ( $new_title ) {
			$title_parts['title'] = $new_title;
		}
		return $title_parts;
	}

	/**
	 * Outputs <meta name="description"> to wp_head.
	 * Skipped if Rank Math or Yoast are active — they manage their own meta output.
	 */
	public static function output_seo_meta() {
		if ( class_exists( 'RankMath' ) || defined( 'WPSEO_VERSION' ) ) {
			return;
		}
		$desc = self::build_seo_description();
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
	}

	/** Rank Math title filter */
	public static function filter_rankmath_title( $title ) {
		$new_title = self::build_seo_title();
		return $new_title ?: $title;
	}

	/** Rank Math description filter */
	public static function filter_rankmath_desc( $desc ) {
		$new_desc = self::build_seo_description();
		return $new_desc ?: $desc;
	}

	/** Yoast SEO title filter */
	public static function filter_yoast_title( $title ) {
		$new_title = self::build_seo_title();
		return $new_title ?: $title;
	}

	/** Yoast SEO description filter */
	public static function filter_yoast_desc( $desc ) {
		$new_desc = self::build_seo_description();
		return $new_desc ?: $desc;
	}

	// -------------------------------------------------------------------------
	// SHORTCODE RENDERER
	// -------------------------------------------------------------------------

	public static function render_hints( $atts ) {
		$atts = shortcode_atts( array(
			'locale' => 'global',
		), $atts, 'wordle_hints' );

		// Get target date (Prioritize 'date' or 'wh_date' URL param)
		$target_date = current_time( 'Y-m-d' );

		if ( isset( $_GET['date'] ) ) {
			$raw = sanitize_text_field( $_GET['date'] );
			// Strict format validation — prevent invalid dates from reaching the DB
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				$target_date = $raw;
			}
		} elseif ( isset( $_GET['wh_date'] ) ) {
			$raw = sanitize_text_field( $_GET['wh_date'] );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				$target_date = $raw;
			}
		} elseif ( strpos( $_SERVER['REQUEST_URI'], 'date=' ) !== false ) {
			preg_match( '/date=(\d{4}-\d{2}-\d{2})/', $_SERVER['REQUEST_URI'], $matches );
			if ( ! empty( $matches[1] ) ) {
				$target_date = $matches[1];
			}
		}

		$puzzle             = Wordle_DB::get_puzzle_by_date( $target_date, $atts['locale'] );
		$is_archive_request = isset( $_GET['date'] ) || isset( $_GET['wh_date'] );

		if ( ! $puzzle && ! $is_archive_request ) {
			// Fallback: get the most recent puzzle UP TO today only.
			// IMPORTANT: Do NOT use get_latest_puzzles() without a date cap — it returns
			// ORDER BY date DESC which would return a FUTURE puzzle (7 days ahead).
			global $wpdb;
			$table = Wordle_DB::get_table_name();
			$today = current_time( 'Y-m-d' );
			$row   = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $table WHERE locale = %s AND date <= %s ORDER BY date DESC LIMIT 1",
				$atts['locale'],
				$today
			), ARRAY_A );
			$puzzle = $row ?: null;
		}

		if ( ! $puzzle ) {
			$puzzle = array(
				'puzzle_number' => '---',
				'date'          => current_time( 'Y-m-d' ),
				'vowel_count'   => '-',
				'first_letter'  => '-',
				'hint1'         => 'Loading...',
				'hint2'         => 'Loading...',
				'hint3'         => 'Loading...',
				'final_hint'    => 'Loading...',
				'word'          => '     ',
			);
		}

		// Store puzzle data so SEO hooks (title, meta desc) can access it
		self::$seo_puzzle = $puzzle;

		ob_start();
		?>
		<div class="wordle-hint-container" id="wordle-hint-pro">
				<div class="wh-header">
					<div class="wh-theme-toggle" id="wh-theme-toggle" title="Toggle Day/Night Mode">
						<span class="wh-toggle-icon">☀️</span>
						<span class="wh-toggle-icon">🌙</span>
						<div class="wh-toggle-knob"></div>
					</div>
					<h1 class="wh-main-title">Today's Wordle Hints</h1>
					<div class="wh-meta">
						<span class="wh-puzzle-num">#<?php echo esc_html( $puzzle['puzzle_number'] ); ?></span>
						<span class="wh-separator">•</span>
						<div class="wh-date-nav">
							<button id="wh-prev-date" class="wh-nav-btn" title="Previous Day">←</button>
							<span class="wh-date" id="wh-calendar-trigger" title="Click to select date"><?php echo date( 'F j, Y', strtotime( $puzzle['date'] ) ); ?></span>
							<input type="text" id="wh-date-picker" style="position:absolute; opacity:0; width:0; height:0; border:none; padding:0; pointer-events:none;" readonly>
							<button id="wh-next-date" class="wh-nav-btn" title="Next Day">→</button>
						</div>
						<span class="wh-separator">•</span>
						<span class="wh-stat-item">Vowels: <span class="wh-highlight"><?php echo esc_html( $puzzle['vowel_count'] ); ?></span></span>
						<span class="wh-separator">•</span>
						<span class="wh-stat-item">Starts With: <span class="wh-highlight"><?php echo esc_html( $puzzle['first_letter'] ); ?></span></span>
					</div>
				</div>

				<div class="wh-hints-section">
					<h3 class="wh-section-title">Wordle Hints</h3>

					<div class="wh-hint-card locked" data-hint="1">
						<div class="wh-hint-overlay">
							<button class="wh-unlock-btn">Unlock Hint 1 (Vague)</button>
						</div>
						<div class="wh-hint-info">
							<span class="wh-hint-label">Hint 1: Vague</span>
							<p class="wh-hint-text"><?php echo esc_html( $puzzle['hint1'] ); ?></p>
						</div>
					</div>

					<div class="wh-hint-card locked" data-hint="2">
						<div class="wh-hint-overlay">
							<button class="wh-unlock-btn">Unlock Hint 2 (Category)</button>
						</div>
						<div class="wh-hint-info">
							<span class="wh-hint-label">Hint 2: Category</span>
							<p class="wh-hint-text"><?php echo esc_html( $puzzle['hint2'] ); ?></p>
						</div>
					</div>

					<div class="wh-hint-card locked" data-hint="3">
						<div class="wh-hint-overlay">
							<button class="wh-unlock-btn">Unlock Hint 3 (Specific)</button>
						</div>
						<div class="wh-hint-info">
							<span class="wh-hint-label">Hint 3: Specific</span>
							<p class="wh-hint-text"><?php echo esc_html( $puzzle['hint3'] ); ?></p>
						</div>
					</div>

					<div class="wh-hint-card locked" data-hint="4">
						<div class="wh-hint-overlay">
							<button class="wh-unlock-btn">Unlock Final Hint</button>
						</div>
						<div class="wh-hint-info">
							<span class="wh-hint-label">Final Hint: Strong</span>
							<p class="wh-hint-text"><?php echo esc_html( $puzzle['final_hint'] ); ?></p>
						</div>
					</div>
				</div>

				<div class="wh-game-section">
					<div class="wh-answer-label">Today's Answer</div>
					<div class="wh-grid" id="wh-answer-grid" data-word="<?php echo esc_attr( $puzzle['word'] ); ?>">
						<?php for ( $i = 0; $i < 5; $i++ ) : ?>
							<div class="wh-box" data-index="<?php echo $i; ?>" title="Click to reveal letter">
								<div class="wh-box-inner">
									<div class="wh-box-front"></div>
									<div class="wh-box-back"></div>
								</div>
							</div>
						<?php endfor; ?>
					</div>

					<div class="wh-reveal-controls">
						<button id="reveal-answer-btn" class="wh-reveal-main-btn">
							<span class="btn-text">Show Today's Answer</span>
						</button>

						<div class="wh-post-reveal-actions">
							<button id="reveal-again-btn" class="wh-secondary-btn">Reveal Again</button>
						</div>
					</div>
				</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
