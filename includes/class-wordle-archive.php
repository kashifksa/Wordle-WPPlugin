<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Archive {

	/**
	 * Render the archive grid shortcode [wordle_archive]
	 */
	public static function render_archive( $atts ) {
		$atts = shortcode_atts( array(
			'limit'      => 24,
			'locale'     => 'global',
			'target_url' => '', // Optional: URL of the page where [wordle_hints] lives
		), $atts, 'wordle_archive' );

		$selected_month = isset( $_GET['wh_month'] ) ? intval( $_GET['wh_month'] ) : 0;
		$selected_year  = isset( $_GET['wh_year'] ) ? intval( $_GET['wh_year'] ) : 0;

		ob_start();
		?>
		<div class="wordle-archive-container" id="wordle-archive-pro">
			<div class="wh-archive-header">
				<h2 class="wh-archive-title">Wordle Archive</h2>
				<p class="wh-archive-subtitle">
					<?php if ( $selected_month && $selected_year ) : ?>
						<?php echo date( 'F Y', mktime( 0, 0, 0, $selected_month, 10, $selected_year ) ); ?>
					<?php else : ?>
						Browse past Wordle puzzles by month
					<?php endif; ?>
				</p>
			</div>

			<?php 
			if ( $selected_month && $selected_year ) {
				self::render_monthly_view( $selected_month, $selected_year, $atts );
			} else {
				self::render_monthly_index( $atts['locale'] );
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the list of months grouped by year.
	 */
	private static function render_monthly_index( $locale ) {
		$available = Wordle_DB::get_available_months( $locale );

		if ( empty( $available ) ) {
			echo '<p class="wh-no-puzzles">No Wordle puzzles found in the archive.</p>';
			return;
		}

		// Group by year
		$grouped = array();
		foreach ( $available as $item ) {
			$grouped[ $item['year'] ][] = $item;
		}

		$years = array_keys( $grouped );

		// Year Jump Navigation (only if multiple years)
		if ( count( $years ) > 1 ) {
			echo '<div class="wh-year-nav">';
			foreach ( $years as $yr ) {
				echo '<a href="#year-' . $yr . '" class="wh-year-jump">' . $yr . '</a>';
			}
			echo '</div>';
		}

		foreach ( $grouped as $year => $months ) {
			echo '<div class="wh-archive-year-section" id="year-' . $year . '">';
			echo '<h3 class="wh-year-title"><span>' . $year . '</span></h3>';
			echo '<div class="wh-archive-grid wh-monthly-index">';
			
			foreach ( $months as $item ) {
				$month_name = date( 'F', mktime( 0, 0, 0, $item['month'], 10 ) );
				$archive_link = add_query_arg( array(
					'wh_month' => $item['month'],
					'wh_year'  => $item['year']
				), get_permalink() );
				?>
				<a href="<?php echo esc_url( $archive_link ); ?>" class="wh-month-card">
					<div class="wh-month-name"><?php echo $month_name; ?></div>
					<div class="wh-month-year"><?php echo $item['year']; ?></div>
					<div class="wh-month-action">View Puzzles →</div>
				</a>
				<?php
			}
			echo '</div>';
			echo '</div>';
		}
	}

	/**
	 * Renders the puzzles for a specific month in a high-end Roundup layout.
	 */
	private static function render_monthly_view( $month, $year, $atts ) {
		$puzzles = Wordle_DB::get_puzzles_for_month( $month, $year, $atts['locale'] );
		$today_date = current_time( 'Y-m-d' );
		$month_name = date( 'F', mktime( 0, 0, 0, $month, 10 ) );
		$base_link = $atts['target_url'] ?: get_permalink();

		echo '<div class="wh-archive-back-nav">';
		echo '<a href="' . esc_url( remove_query_arg( array( 'wh_month', 'wh_year' ) ) ) . '" class="wh-back-btn">← Back to Years</a>';
		echo '</div>';

		if ( empty( $puzzles ) ) {
			echo '<p class="wh-no-puzzles">No puzzles found for ' . $month_name . ' ' . $year . '.</p>';
			return;
		}

		// Calculate Statistics for this specific month
		$total_puzzles = count( $puzzles );
		$avg_diff      = 0;
		$diff_count    = 0;
		$starting_letters = array();

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

		?>
		<div class="wh-roundup-container">
			<div class="wh-roundup-header">
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
				foreach ( $puzzles as $p ) : 
					if ( $p['date'] > $today_date ) continue;

					$formatted_date = date( 'M j', strtotime( $p['date'] ) );
					$diff_val = floatval( $p['difficulty'] );
					if ( $diff_val <= 3.7 ) $diff_label = 'Easy';
					elseif ( $diff_val <= 4.0 ) $diff_label = 'Moderate';
					elseif ( $diff_val <= 4.3 ) $diff_label = 'Hard';
					else $diff_label = 'Insane';

					$puzzle_link = add_query_arg( array( 'date' => $p['date'] ), $base_link );
				?>
					<div class="wh-roundup-row">
						<div class="wh-roundup-date"><?php echo $formatted_date; ?></div>
						<div class="wh-roundup-puzzle">#<?php echo $p['puzzle_number']; ?></div>
						<div class="wh-roundup-word"><?php echo esc_html( strtoupper( $p['word'] ) ); ?></div>
						<div class="wh-roundup-diff wh-diff-<?php echo strtolower( $diff_label ); ?>"><?php echo $diff_label; ?></div>
						<a href="<?php echo esc_url( $puzzle_link ); ?>" class="wh-roundup-link">View Hints →</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
