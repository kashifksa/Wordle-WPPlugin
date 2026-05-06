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

		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		if ( isset( $_GET['wh_page'] ) ) {
			$paged = max( 1, intval( $_GET['wh_page'] ) );
		}

		// We no longer cap by server-time here because we want 
		// the browser to handle it per-user in Step 2.
		$puzzles     = Wordle_DB::get_paginated_puzzles( $paged, $atts['limit'], $atts['locale'] );
		$total_count = Wordle_DB::get_total_puzzles_count( $atts['locale'] );
		$total_pages = ceil( $total_count / $atts['limit'] );

		if ( empty( $puzzles ) ) {
			return '<p class="wh-no-puzzles">No Wordle puzzles found in the archive.</p>';
		}

		$base_link = $atts['target_url'] ?: get_permalink();

		ob_start();
		?>
		<div class="wordle-archive-container" id="wordle-archive-pro">
			<div class="wh-archive-header">
				<h2 class="wh-archive-title">Wordle Archive</h2>
				<p class="wh-archive-subtitle">Browse hints for past Wordle puzzles</p>
			</div>

			<div class="wh-archive-grid">
				<?php foreach ( $puzzles as $puzzle ) : 
					// Build link that preserves the current archive page
					$puzzle_link = add_query_arg( array(
						'date'    => $puzzle['date'],
						'wh_page' => $paged
					), $base_link );
					?>
					<div class="wh-archive-card" data-date="<?php echo esc_attr( $puzzle['date'] ); ?>">
						<div class="wh-card-top">
							<span class="wh-card-num">#<?php echo esc_html( $puzzle['puzzle_number'] ); ?></span>
							<span class="wh-card-date"><?php echo date( 'M j, Y', strtotime( $puzzle['date'] ) ); ?></span>
						</div>
						<div class="wh-card-body">
							<div class="wh-card-stats">
								<span class="wh-stat">Starts: <strong><?php echo esc_html( $puzzle['first_letter'] ); ?></strong></span>
								<span class="wh-stat">Vowels: <strong><?php echo esc_html( $puzzle['vowel_count'] ); ?></strong></span>
							</div>
						</div>
						<div class="wh-card-footer">
							<a href="<?php echo esc_url( $puzzle_link ); ?>" class="wh-archive-link">View Hints</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="wh-archive-pagination">
					<?php if ( $paged > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'wh_page', $paged - 1 ) ); ?>" class="wh-pag-btn prev">← Previous</a>
					<?php endif; ?>
					
					<span class="wh-pag-info">Page <?php echo $paged; ?> of <?php echo $total_pages; ?></span>
					
					<?php if ( $paged < $total_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'wh_page', $paged + 1 ) ); ?>" class="wh-pag-btn next">Next →</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
