<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Solver {

	public static function init() {
		add_shortcode( 'wordle_solver', array( __CLASS__, 'render_solver' ) );
		// Also support dash version if needed
		add_shortcode( 'wordle-solver', array( __CLASS__, 'render_solver' ) );
		
		// Hook into scraper/API refresh to update solver JSON
		add_action( 'wordle_after_scrape', array( __CLASS__, 'generate_solver_json' ) );
	}

	public static function render_solver( $atts ) {
		self::enqueue_assets();
		
		ob_start();
		?>
		<div class="wordle-solver-container" id="wordle-solver">
			<div class="ws-header">
				<h2 class="ws-title">Wordle Solver</h2>
				<p class="ws-subtitle">Master the Grid: Precision <span class="ws-highlight">WORDLE</span> Solver & Intelligent Filtering.</p>
			</div>

			<div class="ws-sections-wrapper">
				<!-- Correct Letters (Green) -->
				<div class="ws-section green-section">
					<div class="ws-section-header">
						<span class="ws-badge green">Correct Letters</span>
						<button class="ws-clear-section" data-target="green"><span class="ws-icon">🗑</span> CLEAR</button>
					</div>
					<div class="ws-row-wrapper">
						<div class="ws-grid ws-green-grid" id="ws-green-grid">
							<?php for($i=0; $i<5; $i++): ?>
								<input type="text" maxlength="1" class="ws-box green" data-pos="<?php echo $i; ?>">
							<?php endfor; ?>
						</div>
					</div>
				</div>

				<!-- Misplaced Letters (Yellow) -->
				<div class="ws-section yellow-section">
					<div class="ws-section-header">
						<span class="ws-badge yellow">Misplaced Letters</span>
						<button class="ws-clear-section" data-target="yellow"><span class="ws-icon">🗑</span> CLEAR</button>
					</div>
					<div class="ws-toggle-wrapper">
						<label class="ws-switch">
							<input type="checkbox" id="ws-exclude-yellow" checked>
							<span class="ws-slider round"></span>
						</label>
						<span class="ws-toggle-label">Exclude yellow letters in these places</span>
					</div>
					<div class="ws-grid-container" id="ws-yellow-grids">
						<div class="ws-row-wrapper">
							<div class="ws-grid ws-yellow-grid">
								<?php for($i=0; $i<5; $i++): ?>
									<input type="text" maxlength="1" class="ws-box yellow" data-pos="<?php echo $i; ?>">
								<?php endfor; ?>
							</div>
						</div>
					</div>
					<button class="ws-add-row" data-target="yellow"><span class="ws-icon">⊕</span> Add Row</button>
				</div>

				<!-- Absent Letters (Gray) -->
				<div class="ws-section gray-section">
					<div class="ws-section-header">
						<span class="ws-badge gray">Absent Letters</span>
						<button class="ws-clear-section" data-target="gray"><span class="ws-icon">🗑</span> CLEAR</button>
					</div>
					<div class="ws-grid-container" id="ws-gray-grids">
						<div class="ws-row-wrapper">
							<div class="ws-grid ws-gray-grid">
								<?php for($i=0; $i<8; $i++): ?>
									<input type="text" maxlength="1" class="ws-box gray">
								<?php endfor; ?>
							</div>
						</div>
					</div>
					<button class="ws-add-row" data-target="gray"><span class="ws-icon">⊕</span> Add Row</button>
				</div>
			</div>

			<!-- Controls -->
			<div class="ws-footer">
				<button id="ws-clear-all" class="ws-btn-secondary">CLEAR ALL</button>
				<button id="ws-solve-btn" class="ws-btn-primary">UPDATE</button>
			</div>

			<!-- Results -->
			<div class="ws-results-section" id="ws-results-section" style="display:none;">
				<div class="ws-results-header">
					<span class="ws-result-count" id="ws-result-count">0 words found</span>
				</div>
				<div class="ws-results-list" id="ws-results-list"></div>
			</div>

			<div class="ws-loader" id="ws-loader" style="display:none;">
				<div class="ws-spinner"></div>
			</div>

			<!-- Alert Toast -->
			<div id="ws-alert" class="ws-alert" style="display:none;">
				<div class="ws-alert-content">
					<span class="ws-alert-icon">!</span>
					<span class="ws-alert-message">Please enter at least one clue to begin filtering results.</span>
				</div>
				<button class="ws-alert-close">&times;</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function enqueue_assets() {
		wp_enqueue_script( 'wordle-solver-script', WORDLE_HINT_URL . 'assets/js/solver.js', array( 'jquery' ), WORDLE_HINT_VERSION, true );
		
		// Ensure JSON exists, if not generate it
		if ( ! file_exists( WORDLE_HINT_PATH . 'wordle-solver-data.json' ) ) {
			self::generate_solver_json();
		}

		wp_localize_script( 'wordle-solver-script', 'wordleSolverData', array(
			'jsonUrl' => WORDLE_HINT_URL . 'wordle-solver-data.json',
		) );
	}

	/**
	 * Merges CSV and DB datasets into one JSON source of truth.
	 */
	public static function generate_solver_json() {
		$words_map = array();
		
		// 1. Load from CSV
		$csv_file = WORDLE_HINT_PATH . 'wordle_dataset_enriched.csv';
		if ( file_exists( $csv_file ) ) {
			if ( ( $handle = fopen( $csv_file, "r" ) ) !== FALSE ) {
				$header = fgetcsv( $handle ); // Skip header
				while ( ( $data = fgetcsv( $handle ) ) !== FALSE ) {
					if ( isset( $data[2] ) ) {
						$word = strtoupper( trim( $data[2] ) );
						if ( strlen( $word ) === 5 ) {
							$words_map[$word] = true;
						}
					}
				}
				fclose( $handle );
			}
		}

		// 2. Load from Database
		global $wpdb;
		$table_name = $wpdb->prefix . 'wordle_data';
		$db_words = $wpdb->get_col( "SELECT word FROM $table_name" );
		
		if ( ! empty( $db_words ) ) {
			foreach ( $db_words as $w ) {
				$word = strtoupper( trim( $w ) );
				if ( strlen( $word ) === 5 ) {
					$words_map[$word] = true;
				}
			}
		}

		// 3. Extract and Sort
		$words = array_keys( $words_map );
		sort( $words );

		// 4. Save to JSON
		$final_data = array(
			'words' => $words,
			'last_updated' => current_time( 'mysql' )
		);

		file_put_contents( WORDLE_HINT_PATH . 'wordle-solver-data.json', json_encode( $final_data ) );
		
		return count( $words );
	}
}
