<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Frontend {

	public static function render_hints( $atts ) {
		$atts = shortcode_atts( array(
			'locale' => 'global',
		), $atts, 'wordle_hints' );

		// Get today's puzzle
		$today = current_time( 'Y-m-d' );
		$puzzle = Wordle_DB::get_puzzle_by_date( $today, $atts['locale'] );

		if ( ! $puzzle ) {
			// Try getting the latest one if today's is missing
			$latest = Wordle_DB::get_latest_puzzles( 1, $atts['locale'] );
			$puzzle = ! empty( $latest ) ? $latest[0] : null;
		}

		ob_start();
		?>
		<div class="wordle-hint-container" id="wordle-hint-pro">
			<?php if ( ! $puzzle ) : ?>
				<div class="wh-no-data">
					<p>No puzzle data available for today yet. Please check back later!</p>
				</div>
			<?php else : ?>
				<div class="wh-theme-toggle" id="wh-theme-toggle" title="Toggle Day/Night Mode">
					<span class="wh-toggle-icon">☀️</span>
					<span class="wh-toggle-icon">🌙</span>
					<div class="wh-toggle-knob"></div>
				</div>
				<div class="wh-header">

					<h1 class="wh-main-title">Today's Wordle Hints</h1>
					<div class="wh-meta">
						<span class="wh-puzzle-num">#<?php echo esc_html( $puzzle['puzzle_number'] ); ?></span>
						<span class="wh-separator">•</span>
						<span class="wh-date"><?php echo date( 'F j, Y', strtotime( $puzzle['date'] ) ); ?></span>
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
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
