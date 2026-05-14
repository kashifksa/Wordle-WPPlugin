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
		add_action( 'wp_footer',            array( __CLASS__, 'output_faq_schema' ), 100 );

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

		// Open Graph Image (Social Hint Card)
		if ( self::$seo_puzzle && self::$seo_puzzle['puzzle_number'] !== '---' ) {
			$share_image_url = get_rest_url( null, 'wordle/v1/share-image/' . self::$seo_puzzle['date'] );
			echo '<meta property="og:image" content="' . esc_url( $share_image_url ) . '">' . "\n";
			echo '<meta property="og:image:width" content="1200">' . "\n";
			echo '<meta property="og:image:height" content="630">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $share_image_url ) . '">' . "\n";
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
	// SHORTCODE RENDERERS
	// -------------------------------------------------------------------------

	/**
	 * Renders a monthly roundup list of puzzles.
	 * Usage: [wordle_monthly_roundup month="05" year="2026"]
	 */
	public static function render_monthly_roundup( $atts ) {
		$atts = shortcode_atts( array(
			'month'  => current_time( 'm' ),
			'year'   => current_time( 'Y' ),
			'locale' => 'global',
		), $atts, 'wordle_monthly_roundup' );

		$puzzles = Wordle_DB::get_puzzles_for_month( $atts['month'], $atts['year'], $atts['locale'] );
		$month_name = date( 'F', mktime( 0, 0, 0, $atts['month'], 10 ) );

		if ( empty( $puzzles ) ) {
			return "<div class='wh-no-puzzles'>No puzzles found for {$month_name} {$atts['year']}.</div>";
		}

		// Calculate Statistics
		$total_puzzles = count( $puzzles );
		$avg_diff      = 0;
		$diff_count    = 0;
		$starting_letters = [];

		foreach ( $puzzles as $p ) {
			if ( ! empty( $p['difficulty'] ) ) {
				$avg_diff += floatval( $p['difficulty'] );
				$diff_count++;
			}
			if ( ! empty( $p['first_letter'] ) ) {
				$starting_letters[] = $p['first_letter'];
			}
		}

		$final_avg_diff = $diff_count > 0 ? round( $avg_diff / $diff_count, 2 ) : 'N/A';
		$counts = array_count_values( $starting_letters );
		arsort( $counts );
		$top_letter = ! empty( $counts ) ? array_key_first( $counts ) : 'N/A';

		ob_start();
		?>
		<div class="wh-roundup-container">
			<div class="wh-roundup-header">
				<h2 class="wh-roundup-title">Wordle Answers: <?php echo "{$month_name} {$atts['year']}"; ?></h2>
				<div class="wh-roundup-stats">
					<div class="wh-roundup-stat-box">
						<span class="wh-stat-label">Avg. Difficulty</span>
						<span class="wh-stat-val"><?php echo $final_avg_diff; ?></span>
					</div>
					<div class="wh-roundup-stat-box">
						<span class="wh-stat-label">Total Puzzles</span>
						<span class="wh-stat-val"><?php echo $total_puzzles; ?></span>
					</div>
					<div class="wh-roundup-stat-box">
						<span class="wh-stat-label">Top Start Letter</span>
						<span class="wh-stat-val"><?php echo $top_letter; ?></span>
					</div>
				</div>
			</div>

			<div class="wh-roundup-list">
				<?php 
				$today_date = current_time( 'Y-m-d' );
				foreach ( $puzzles as $p ) : 
					// Future Guard: Skip if date is ahead of site time
					if ( $p['date'] > $today_date ) continue;

					$formatted_date = date( 'M j', strtotime( $p['date'] ) );
					$diff_val = floatval( $p['difficulty'] );
					if ( $diff_val <= 3.7 ) $diff_label = 'Easy';
					elseif ( $diff_val <= 4.0 ) $diff_label = 'Moderate';
					elseif ( $diff_val <= 4.3 ) $diff_label = 'Hard';
					else $diff_label = 'Insane';
				?>
					<div class="wh-roundup-row" data-date="<?php echo $p['date']; ?>">
						<div class="wh-roundup-date"><?php echo $formatted_date; ?></div>
						<div class="wh-roundup-puzzle">#<?php echo $p['puzzle_number']; ?></div>
						<div class="wh-roundup-word"><?php echo esc_html( strtoupper( $p['word'] ) ); ?></div>
						<div class="wh-roundup-diff wh-diff-<?php echo strtolower( $diff_label ); ?>"><?php echo $diff_label; ?></div>
						<a href="?wh_date=<?php echo $p['date']; ?>" class="wh-roundup-link">View Hints →</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

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
		
		$today_date = current_time( 'Y-m-d' );
		$is_future = ( $target_date > $today_date );
		$is_admin = current_user_can( 'manage_options' );

		// SPOILER GUARD: Block future puzzles for non-admins
		if ( $is_future && ! $is_admin ) {
			// Fetch to get puzzle number but scrub sensitive data
			$puzzle = Wordle_DB::get_puzzle_by_date( $target_date, $atts['locale'] );
			if ( $puzzle ) {
				$puzzle['word'] = '?????';
				$puzzle['hint1'] = 'This hint is locked until ' . date( 'M j', strtotime( $target_date ) ) . '.';
				$puzzle['hint2'] = 'The clues will be revealed when your local clock hits midnight.';
				$puzzle['hint3'] = 'Stay tuned!';
				$puzzle['final_hint'] = 'Hidden until release.';
				$puzzle['definition'] = 'Locked';
				$puzzle['etymology'] = 'Locked';
			}
		} else {
			$puzzle = Wordle_DB::get_puzzle_by_date( $target_date, $atts['locale'] );
		}

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
		<div class="wordle-hint-container <?php echo $is_future ? 'wh-is-future' : ''; ?>" id="wordle-hint-pro" data-is-future="<?php echo $is_future ? '1' : '0'; ?>">
			<?php if ( $is_future && ! $is_admin ) : ?>
				<div class="wh-future-overlay">
					<div class="wh-future-notice">
						<span class="wh-future-icon">⏳</span>
						<h2>Coming Soon</h2>
						<p>This puzzle is locked. It will be revealed on <strong><?php echo date( 'F j, Y', strtotime( $target_date ) ); ?></strong>.</p>
						<p class="wh-future-timezone-note">Reveals automatically based on your local timezone.</p>
						<a href="<?php echo home_url(); ?>" class="wh-back-home">View Today's Hints</a>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( $is_future && $is_admin ) : ?>
				<div class="wh-admin-preview-notice" style="background: #ffc107; color: #000; padding: 10px; text-align: center; font-weight: bold; border-radius: 8px 8px 0 0;">
					⚠️ ADMIN PREVIEW: This puzzle is scheduled for <?php echo $target_date; ?>.
				</div>
			<?php endif; ?>

				<div class="wh-header">
					<div class="wh-theme-toggle" id="wh-theme-toggle" title="Toggle Day/Night Mode" role="button" tabindex="0" aria-label="Toggle between dark and light mode">
						<span class="wh-toggle-icon">☀️</span>
						<span class="wh-toggle-icon">🌙</span>
						<div class="wh-toggle-knob"></div>
					</div>
					<h1 class="wh-main-title">Today's Wordle Hints</h1>
					<div class="wh-meta">
						<span class="wh-puzzle-num">#<?php echo esc_html( $puzzle['puzzle_number'] ); ?></span>
						<?php 
						if ( ! empty( $puzzle['difficulty'] ) ) {
							$diff_val = floatval( $puzzle['difficulty'] );
							$diff_label = 'Moderate';
							if ( $diff_val <= 2.2 ) $diff_label = 'Very Easy';
							elseif ( $diff_val <= 3.2 ) $diff_label = 'Moderate';
							elseif ( $diff_val <= 4.4 ) $diff_label = 'Hard';
							else $diff_label = 'Insane';
							?>
							<span class="wh-difficulty-badge" data-value="<?php echo esc_attr( $puzzle['difficulty'] ); ?>">
								<span class="wh-difficulty-dot"></span>
								<span class="wh-difficulty-label"><?php echo esc_html( $diff_label ); ?></span>
							</span>
						<?php } ?>
						<span class="wh-separator">•</span>
						<div class="wh-date-nav">
							<button id="wh-prev-date" class="wh-nav-btn" title="Previous Day" aria-label="Go to previous day's Wordle">←</button>
							<span class="wh-date" id="wh-calendar-trigger" data-current-date="<?php echo esc_attr( $puzzle['date'] ); ?>" title="Jump to Any Past Wordle" role="button" tabindex="0" aria-label="Open calendar to pick a date"><?php echo date( 'F j, Y', strtotime( $puzzle['date'] ) ); ?></span>
							<input type="text" id="wh-date-picker" style="position:absolute; opacity:0; width:0; height:0; border:none; padding:0; pointer-events:none;" readonly>
							<button id="wh-next-date" class="wh-nav-btn" title="Next Day" aria-label="Go to next day's Wordle">→</button>
						</div>
						<span class="wh-separator">•</span>
						<span class="wh-stat-item">Vowels: <span class="wh-highlight"><?php echo esc_html( $puzzle['vowel_count'] ); ?></span></span>
					</div>
				</div>

				<div class="wh-stats-summary" id="wh-stats-summary" style="<?php echo empty( $puzzle['average_guesses'] ) ? 'display:none;' : ''; ?>">
					<div class="wh-stats-header">
						<span class="wh-stats-title">Wordle Bot Analytics</span>
						<span class="wh-stats-avg">Global Avg: <strong><?php echo esc_html( $puzzle['average_guesses'] ); ?></strong> guesses</span>
					</div>
					<div class="wh-dist-chart">
						<?php 
						$dist = json_decode( $puzzle['guess_distribution'] ?? '[]', true );
						// Always render 6 placeholders for AJAX compatibility
						for ( $i = 0; $i < 6; $i++ ) {
							$pct = isset( $dist[$i] ) ? $dist[$i] : 0;
							$label = $i + 1;
							echo "<div class='wh-dist-bar-wrapper' title='{$pct}% solved in {$label}'>";
							echo "<div class='wh-dist-label'>{$label}</div>";
							echo "<div class='wh-dist-bar-container'><div class='wh-dist-bar' style='width:{$pct}%'></div></div>";
							echo "<div class='wh-dist-pct'>{$pct}%</div>";
							echo "</div>";
						}
						?>
					</div>
				</div>
				<!-- Strategy Insight Card -->
				<?php 
				$insight = Wordle_Analyzer::get_insight_for_word( $puzzle['word'] );
				if ( ! empty( $insight ) ) : 
				?>
					<div class="wh-strategy-card" id="wh-strategy-insight">
						<div class="wh-strategy-icon">💡</div>
						<div class="wh-strategy-content">
							<span class="wh-strategy-label">Strategy Corner</span>
							<p class="wh-strategy-text"><?php echo $insight; ?></p>
						</div>
					</div>
				<?php endif; ?>


				<!-- Hints Section -->
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
							<div class="wh-box" data-index="<?php echo $i; ?>" title="Click to reveal letter" role="button" tabindex="0" aria-label="Reveal Wordle letter <?php echo $i + 1; ?>">
								<div class="wh-box-inner">
									<div class="wh-box-front"></div>
									<div class="wh-box-back"></div>
								</div>
							</div>
						<?php endfor; ?>
					</div>

					<div class="wh-reveal-controls">
						<button id="reveal-answer-btn" class="wh-reveal-main-btn" aria-label="Instantly reveal the full Wordle answer">
							<span class="btn-text">Show Today's Answer</span>
						</button>

						<!-- New Smart Action Toolbar -->
						<div id="wh-post-reveal-toolbar" class="wh-post-reveal-toolbar wh-hidden">
							<div class="wh-toolbar-segment wh-nav-segment">
								<?php 
								$is_today = ( $puzzle['date'] === date( 'Y-m-d' ) );
								$nav_label = ( $puzzle['date'] === date( 'Y-m-d' ) ) ? 'Previous Day' : 'Previous Day'; // Force consistency as requested
								?>
								<button id="wh-toolbar-prev" class="wh-toolbar-btn" title="Go to Previous Day's Wordle" aria-label="View previous day's Wordle">
									<span class="icon">←</span> <span class="label">Previous Day</span>
								</button>
								<button id="wh-toolbar-calendar" class="wh-toolbar-btn" title="Jump to Any Past Wordle" aria-label="Open calendar selector">
									<i data-lucide="calendar-days"></i>
								</button>
							</div>

							<div class="wh-toolbar-segment wh-share-segment">
								<button id="wh-copy-results" class="wh-toolbar-btn" title="Copy Emoji Results" aria-label="Copy your Wordle result emoji grid">
									<i data-lucide="copy"></i>
								</button>
								<button id="wh-download-card" class="wh-share-btn" style="display:none;" title="Download Hint Card for Social Media" data-date="<?php echo esc_attr($puzzle['date']); ?>">
									<i data-lucide="share-2"></i> <span class="label">Share Card</span>
								</button>
								<button id="wh-download-story" class="wh-share-btn" title="Download Share Card (Mobile Optimized)" data-date="<?php echo esc_attr($puzzle['date']); ?>">
									<i data-lucide="share-2"></i> <span class="label">Share Card</span>
								</button>
							</div>

							<div class="wh-toolbar-segment wh-timer-segment">
								<div class="wh-next-countdown">
									<span class="next-label">NEXT WORDLE</span>
									<span class="wh-timer-val next-time">00:00:00</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="wh-discovery-section" id="wh-discovery-section">
					<div class="wh-discovery-header">
						<h3 class="wh-discovery-title">Word Discovery</h3>
						<div class="wh-discovery-meta">
							<span class="wh-pos" id="wh-pos"><?php echo esc_html( $puzzle['part_of_speech'] ?? '' ); ?></span>
							<span class="wh-separator">•</span>
							<span class="wh-pronunciation" id="wh-pronunciation"><?php echo esc_html( $puzzle['pronunciation'] ?? '' ); ?></span>
							<?php if ( ! empty( $puzzle['audio_url'] ) ) : ?>
								<button class="wh-audio-btn" id="wh-audio-btn" data-src="<?php echo esc_url( $puzzle['audio_url'] ); ?>" title="Listen to pronunciation">
									<span class="wh-audio-icon">🔊</span>
								</button>
								<audio id="wh-pronunciation-audio" style="display:none;"></audio>
							<?php endif; ?>
						</div>
					</div>

					<div class="wh-discovery-grid">
						<div class="wh-discovery-item wh-span-all">
							<div class="wh-discovery-label">Definition</div>
							<p class="wh-discovery-text" id="wh-definition"><?php echo esc_html( $puzzle['definition'] ?? '' ); ?></p>
						</div>

						<div class="wh-discovery-item wh-span-all wh-example-box" id="wh-example-wrapper" style="<?php echo empty($puzzle['example_sentence']) ? 'display:none;' : ''; ?>">
							<div class="wh-discovery-label">Example Usage</div>
							<p class="wh-discovery-text italic" id="wh-example"><?php echo esc_html( $puzzle['example_sentence'] ?? '' ); ?></p>
						</div>

						<div class="wh-discovery-item">
							<div class="wh-discovery-label">Synonyms</div>
							<div class="wh-tag-container" id="wh-synonyms">
								<?php 
								$syns = json_decode( $puzzle['synonyms'] ?? '[]', true );
								if ( ! empty( $syns ) ) {
									foreach ( array_slice( $syns, 0, 8 ) as $syn ) {
										echo '<span class="wh-tag">' . esc_html( $syn ) . '</span>';
									}
								}
								?>
							</div>
						</div>

						<div class="wh-discovery-item">
							<div class="wh-discovery-label">Antonyms</div>
							<div class="wh-tag-container" id="wh-antonyms">
								<?php 
								$ants = json_decode( $puzzle['antonyms'] ?? '[]', true );
								if ( ! empty( $ants ) ) {
									foreach ( array_slice( $ants, 0, 8 ) as $ant ) {
										echo '<span class="wh-tag wh-tag-alt">' . esc_html( $ant ) . '</span>';
									}
								}
								?>
							</div>
						</div>

						<div class="wh-discovery-item wh-span-all">
							<div class="wh-discovery-label">Etymology & History</div>
							<p class="wh-discovery-text small" id="wh-etymology"><?php echo esc_html( $puzzle['etymology'] ?? '' ); ?></p>
							<div class="wh-first-use" id="wh-first-use-wrapper" style="<?php echo empty($puzzle['first_known_use']) ? 'display:none;' : ''; ?>">First Known Use: <strong id="wh-first-use"><?php echo esc_html( $puzzle['first_known_use'] ?? '' ); ?></strong></div>
						</div>
					</div>
				</div>

				<!-- Historical Trivia (On This Day) -->
				<?php 
				$historical_dates = [
					'1 year ago'  => date( 'Y-m-d', strtotime( '-1 year', strtotime( $puzzle['date'] ) ) ),
					'2 years ago' => date( 'Y-m-d', strtotime( '-2 years', strtotime( $puzzle['date'] ) ) ),
				];
				$history = [];
				foreach ( $historical_dates as $label => $h_date ) {
					$h_puzzle = Wordle_DB::get_puzzle_by_date( $h_date, $atts['locale'] );
					if ( $h_puzzle ) {
						$history[$label] = $h_puzzle;
					}
				}
				if ( ! empty( $history ) ) : 
				?>
					<div class="wh-history-section" style="margin-top:20px; padding-top:20px; border-top:1px solid #eee;">
						<h3 class="wh-section-title" style="text-align:center; font-size:12px; letter-spacing:2px; color:#888; margin-bottom:15px;">WORDLE ON THIS DAY</h3>
						<div class="wh-timeline-container" style="display:flex; flex-direction:column; gap:8px;">
							<?php foreach ( $history as $label => $h_p ) : ?>
								<a href="?wh_date=<?php echo esc_attr( $h_p['date'] ); ?>" class="wh-compact-card" style="display:flex !important; align-items:center !important; justify-content:space-between !important; background:#f9f9f9; border:1px solid #eee; border-radius:10px; padding:8px 15px; text-decoration:none !important; transition:all 0.3s ease;">
									<div style="display:flex; align-items:center; gap:12px;">
										<span style="font-size:10px; font-weight:900; color:#c9b458; background:rgba(201,180,88,0.1); padding:2px 6px; border-radius:4px; width: 85px; text-align: center; display: inline-block;"><?php echo esc_html( strtoupper( $label ) ); ?></span>
										<span style="font-size:14px; font-weight:700; color:#333;">Wordle #<?php echo esc_html( $h_p['puzzle_number'] ); ?></span>
									</div>
									<div style="display:flex; align-items:center; gap:8px;">
										<span style="font-size:12px; color:#888;"><?php echo date( 'M j, Y', strtotime( $h_p['date'] ) ); ?></span>
										<span style="font-size:16px; color:#ccc;">→</span>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
	/**
	 * Outputs JSON-LD FAQ schema to the footer based on the active puzzle.
	 */
	public static function output_faq_schema() {
		if ( ! self::$seo_puzzle || self::$seo_puzzle['puzzle_number'] === '---' ) {
			return;
		}

		$puzzle = self::$seo_puzzle;
		$date_str = self::get_formatted_date( $puzzle );
		$number = $puzzle['puzzle_number'];
		
		$questions = array(
			array(
				'question' => "What are the Wordle hints for today, {$date_str}?",
				'answer'   => "Today's Wordle #{$number} hints include: 1. " . esc_html($puzzle['hint1']) . " 2. " . esc_html($puzzle['hint2']) . " 3. " . esc_html($puzzle['hint3']) . " and a final clue: " . esc_html($puzzle['final_hint']) . "."
			),
			array(
				'question' => "What does the Wordle word for today start with?",
				'answer'   => "The Wordle word for puzzle #{$number} starts with the letter '" . esc_html($puzzle['first_letter']) . "'."
			),
			array(
				'question' => "How many vowels are in today's Wordle?",
				'answer'   => "There are " . esc_html($puzzle['vowel_count']) . " vowel(s) in today's Wordle word."
			)
		);

		// Add dictionary FAQ if available
		if ( ! empty( $puzzle['definition'] ) ) {
			$questions[] = array(
				'question' => "What is the definition of today's Wordle word?",
				'answer'   => "The word is defined as: " . esc_html($puzzle['definition']) . "."
			);
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'FAQPage',
			'mainEntity' => array()
		);

		foreach ( $questions as $q ) {
			$schema['mainEntity'][] = array(
				'@type' => 'Question',
				'name'  => $q['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $q['answer']
				)
			);
		}

		echo "\n<!-- Wordle Hint Pro: FAQ Schema -->\n";
		echo '<script type="application/ld+json">' . json_encode( $schema ) . '</script>' . "\n";
	}

	/**
	 * Renders a standalone subscription form.
	 * Usage: [wordle_subscription]
	 */
	public static function render_subscription_form() {
		ob_start();
		?>
		<div class="wordle-hint-container wh-standalone-subscribe">
			<div class="wh-subscribe-widget" id="wh-subscribe-widget">
				<div class="wh-subscribe-icon">
					<i data-lucide="bell"></i>
				</div>
				<div class="wh-subscribe-content">
					<h4 class="wh-subscribe-title">Never Miss a Hint</h4>
					<p class="wh-subscribe-text">Get daily Wordle insights delivered to your inbox every morning.</p>
					<form id="wh-subscribe-form" class="wh-subscribe-form">
						<input type="email" name="email" placeholder="Enter your email..." required aria-label="Email Address">
						<button type="submit" class="wh-subscribe-btn">
							<span class="wh-btn-text">Subscribe</span>
							<span class="wh-btn-loading" style="display:none;">⏳</span>
						</button>
					</form>
					<div id="wh-subscribe-message" class="wh-subscribe-message"></div>
				</div>
			</div>
		</div>
		<script>
			// Ensure Lucide icons are rendered for standalone shortcode
			if (typeof lucide !== 'undefined') {
				lucide.createIcons();
			}
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders a standalone countdown timer.
	 */
	public static function render_timer( $atts ) {
		ob_start();
		?>
		<div class="wh-standalone-timer">
			<div class="wh-next-countdown">
				<span class="next-label">NEXT WORDLE IN</span>
				<span class="wh-timer-val next-time">00:00:00</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
