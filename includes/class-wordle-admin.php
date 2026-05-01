<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_admin_menu() {
		add_menu_page(
			'Wordle Hint Settings',
			'Wordle Hint',
			'manage_options',
			'wordle-hint-settings',
			array( __CLASS__, 'settings_page' ),
			'dashicons-lightbulb'
		);
	}

	public static function register_settings() {
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_scrape_url' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_api_key' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_model' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_api_key_fallback' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_model_fallback' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_prompt' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_api_key' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_timezone' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_cron_schedule' );
	}

	public static function settings_page() {
		?>
		<div class="wrap">
			<h1>Wordle Hint Pro Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wordle_hint_settings_group' ); ?>
				<?php do_settings_sections( 'wordle_hint_settings_group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Scrape URL</th>
						<td>
							<input type="text" name="wordle_hint_scrape_url" value="<?php echo esc_attr( get_option( 'wordle_hint_scrape_url' ) ); ?>" placeholder="https://www.nytimes.com/svc/wordle/v2/" class="large-text" />
							<p class="description">Leave empty to use the default NYT Wordle v2 endpoint. The date will be appended automatically.</p>
						</td>
					</tr>
					<tr valign="top" style="background: #f9f9f9; border-top: 1px solid #ddd; border-bottom: 1px solid #ddd;">
						<th scope="row"><h3>Manual Wordle Entry</h3></th>
						<td>
							<div class="manual-entry-container" style="padding: 10px 0;">
								<div style="margin-bottom: 10px;">
									<label for="manual_wordle_word"><strong>Wordle Word:</strong></label><br>
									<input type="text" id="manual_wordle_word" maxlength="5" class="regular-text" style="text-transform: uppercase;" placeholder="ENTER" />
									<p class="description">Exactly 5 letters.</p>
								</div>
								<div style="margin-bottom: 10px;">
									<label for="manual_wordle_number"><strong>Wordle Number:</strong></label><br>
									<input type="number" id="manual_wordle_number" class="regular-text" placeholder="1234" />
								</div>
								<div style="margin-bottom: 10px;">
									<label for="manual_wordle_date"><strong>Date:</strong></label><br>
									<input type="date" id="manual_wordle_date" value="<?php echo current_time( 'Y-m-d' ); ?>" class="regular-text" />
								</div>
								<button type="button" id="save-manual-wordle" class="button button-secondary">Save Wordle Entry</button>
								<div id="manual-status-msg" style="margin-top: 10px; font-weight: bold;"></div>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Primary AI API Key (Groq/OpenAI/Gemini)</th>
						<td><input type="password" name="wordle_hint_ai_api_key" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_api_key' ) ); ?>" class="large-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Primary AI Model</th>
						<td><input type="text" name="wordle_hint_ai_model" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_model', 'llama-3.1-8b-instant' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top" style="border-top: 1px dashed #ccc;">
						<th scope="row">Fallback AI API Key (Optional)</th>
						<td><input type="password" name="wordle_hint_ai_api_key_fallback" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_api_key_fallback' ) ); ?>" class="large-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Fallback AI Model</th>
						<td><input type="text" name="wordle_hint_ai_model_fallback" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_model_fallback' ) ); ?>" class="regular-text" placeholder="e.g. gemini-1.5-flash" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">AI Prompt Template</th>
						<td>
							<textarea name="wordle_hint_ai_prompt" rows="6" class="large-text"><?php echo esc_textarea( get_option( 'wordle_hint_ai_prompt', "Generate 4 progressive Wordle hints for the word {{WORD}}.\n\nRules:\n- FORBIDDEN: Do NOT use the word '{{WORD}}' or its plural.\n- FORBIDDEN: Do NOT use direct synonyms.\n- FORBIDDEN: Do NOT mention it has 5 letters (this is implied).\n- FORBIDDEN: Do NOT use rhymes.\n\nHint 1: Cryptic/Vague\nHint 2: Category/Context\nHint 3: Definition-style clue\nHint 4: Strong final hint" ) ); ?></textarea>
							<p class="description">Use {{WORD}} as a placeholder. Be strict with the AI rules for better clues.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Internal API Key (for protection)</th>
						<td><input type="text" name="wordle_hint_api_key" value="<?php echo esc_attr( get_option( 'wordle_hint_api_key' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Timezone</th>
						<td><input type="text" name="wordle_hint_timezone" value="<?php echo esc_attr( get_option( 'wordle_hint_timezone', 'Asia/Karachi' ) ); ?>" class="regular-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			
			<hr>
			<h2>System Status</h2>
			<table class="wp-list-table widefat fixed striped">
				<tr>
					<td><strong>JSON Cache Status:</strong></td>
					<td>
						<?php 
						$file = WORDLE_HINT_PATH . 'wordle-cache.json';
						if (file_exists($file)) {
							echo '✔ Last updated: ' . date('F j, Y, g:i a', filemtime($file));
						} else {
							echo '❌ Cache missing. Click "Fetch & Save JSON" below.';
						}
						?>
					</td>
				</tr>
			</table>

			<hr>
			<h2>Actions</h2>
			<button id="run-scraper-now" class="button button-primary">Run Scraper Now</button>
			<button id="fetch-save-json" class="button button-primary">Fetch & Save JSON</button>
			<button id="regenerate-fallbacks" class="button button-secondary">Regenerate Fallback Hints</button>
			<button id="test-ai-connection" class="button button-secondary">Test Primary AI</button>
			<button id="test-fallback-ai" class="button button-secondary">Test Fallback AI</button>
			<div id="scraper-log" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; display:none;"></div>
			
			<script>
			jQuery(document).ready(function($) {
				$('#run-scraper-now').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Running...');
					$log.show().html('Starting scraper...');
					
					$.post(ajaxurl, {
						action: 'run_wordle_scraper',
						nonce: '<?php echo wp_create_nonce("wordle_scraper_nonce"); ?>'
					}, function(response) {
						$log.append('<br>' + response.data.message);
						$btn.prop('disabled', false).text('Run Scraper Now');
					});
				});

				$('#fetch-save-json').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Refreshing...');
					$log.show().html('Starting JSON cache generation...');
					
					$.post({
						url: '<?php echo get_rest_url(null, "wordle/v1/refresh-json"); ?>',
						beforeSend: function(xhr) {
							xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
						},
						success: function(response) {
							$log.append('<br>Success: ' + response.message);
						},
						error: function() {
							$log.append('<br>Error: Failed to refresh JSON cache');
						},
						complete: function() {
							$btn.prop('disabled', false).text('Fetch & Save JSON');
						}
					});
				});

				$('#save-manual-wordle').click(function() {
					var $btn = $(this);
					var $status = $('#manual-status-msg');
					var word = $('#manual_wordle_word').val().trim().toUpperCase();
					var number = $('#manual_wordle_number').val();
					var date = $('#manual_wordle_date').val();

					if (word.length !== 5) {
						$status.css('color', 'red').text('❌ Word must be exactly 5 letters.');
						return;
					}
					if (!number) {
						$status.css('color', 'red').text('❌ Please enter a Wordle number.');
						return;
					}
					if (!date) {
						$status.css('color', 'red').text('❌ Please select a date.');
						return;
					}

					$btn.prop('disabled', true).text('Saving...');
					$status.css('color', 'blue').text('Saving data...');

					$.post(ajaxurl, {
						action: 'save_manual_wordle',
						nonce: '<?php echo wp_create_nonce("wordle_manual_nonce"); ?>',
						word: word,
						number: number,
						date: date
					}, function(response) {
						if (response.success) {
							$status.css('color', 'green').text('✔ ' + response.data.message);
							// Trigger cache refresh
							$('#fetch-save-json').click();
						} else {
							$status.css('color', 'red').text('❌ ' + response.data.message);
						}
						$btn.prop('disabled', false).text('Save Wordle Entry');
					});
				});

				$('#regenerate-fallbacks').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Regenerating...');
					$log.show().html('Scanning for low-quality fallback hints...');
					
					$.post(ajaxurl, {
						action: 'regenerate_wordle_fallbacks',
						nonce: '<?php echo wp_create_nonce("wordle_regen_nonce"); ?>'
					}, function(response) {
						$log.append('<br>' + response.data.message);
						if (response.success) {
							$('#fetch-save-json').click(); // Refresh JSON too
						}
						$btn.prop('disabled', false).text('Regenerate Fallback Hints');
					});
				});

				$('#test-ai-connection').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Testing...');
					$log.show().html('Testing Primary AI connection with model: ' + $('input[name="wordle_hint_ai_model"]').val() + '...');
					
					$.post(ajaxurl, {
						action: 'test_wordle_ai',
						nonce: '<?php echo wp_create_nonce("wordle_test_ai_nonce"); ?>'
					}, function(response) {
						if (response.success) {
							$log.append('<br><span style="color:green;">✔ Primary Success!</span> AI hints generated: ' + JSON.stringify(response.data.hints));
						} else {
							$log.append('<br><span style="color:red;">❌ Primary Error:</span> ' + response.data.message);
						}
						$btn.prop('disabled', false).text('Test Primary AI');
					});
				});
				
				$('#test-fallback-ai').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Testing...');
					$log.show().html('Testing Fallback AI connection with model: ' + $('input[name="wordle_hint_ai_model_fallback"]').val() + '...');
					
					$.post(ajaxurl, {
						action: 'test_wordle_fallback_ai',
						nonce: '<?php echo wp_create_nonce("wordle_test_fallback_nonce"); ?>'
					}, function(response) {
						if (response.success) {
							$log.append('<br><span style="color:green;">✔ Fallback Success!</span> AI hints generated: ' + JSON.stringify(response.data.hints));
						} else {
							$log.append('<br><span style="color:red;">❌ Fallback Error:</span> ' + response.data.message);
						}
						$btn.prop('disabled', false).text('Test Fallback AI');
					});
				});
			});
			</script>
		</div>
		<?php
	}
}

Wordle_Admin::init();

// AJAX handler for manual run
add_action( 'wp_ajax_run_wordle_scraper', function() {
	check_ajax_referer( 'wordle_scraper_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	$result = Wordle_Scheduler::run_job();
	
	if ( $result['success'] ) {
		wp_send_json_success( array( 'message' => 'Success: ' . $result['message'] ) );
	} else {
		wp_send_json_error( array( 'message' => 'Failed: ' . $result['message'] ) );
	}
} );

// AJAX handler for manual wordle entry
add_action( 'wp_ajax_save_manual_wordle', function() {
	check_ajax_referer( 'wordle_manual_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	$word = strtoupper( sanitize_text_field( $_POST['word'] ) );
	$number = intval( $_POST['number'] );
	$date = sanitize_text_field( $_POST['date'] );

	if ( strlen( $word ) !== 5 ) {
		wp_send_json_error( array( 'message' => 'Word must be exactly 5 letters' ) );
	}

	if ( ! $number || ! $date ) {
		wp_send_json_error( array( 'message' => 'Missing required fields' ) );
	}

	// Prepare data for DB
	$data = array(
		'word'          => $word,
		'puzzle_number' => $number,
		'date'          => $date,
		'entry_source'  => 'manual',
	);

	// Analyze word
	$analysis = Wordle_Scraper::analyze_word( $word );
	$data = array_merge( $data, $analysis );

	// Generate hints
	$hints = Wordle_AI::generate_hints( $word );
	$ai_status = 'AI hints generated';
	
	if ( ! is_wp_error( $hints ) ) {
		$data = array_merge( $data, $hints );
	} else {
		$ai_status = 'AI Error: ' . $hints->get_error_message() . ' (Fallback hints used)';
		$data = array_merge( $data, Wordle_Scraper::generate_fallback_hints( $word ) );
	}

	// Save to DB (UPSERT)
	$result = Wordle_DB::insert_puzzle( $data );
	
	if ( $result !== false ) {
		wp_send_json_success( array( 'message' => 'Wordle data saved successfully. ' . $ai_status ) );
	} else {
		wp_send_json_error( array( 'message' => 'Database error or no changes made' ) );
	}
} );

// AJAX handler to regenerate fallbacks
add_action( 'wp_ajax_regenerate_wordle_fallbacks', function() {
	check_ajax_referer( 'wordle_regen_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	global $wpdb;
	$table = Wordle_DB::get_table_name();
	
	// Find entries with the old basic fallback pattern
	$fallbacks = $wpdb->get_results( "SELECT * FROM $table WHERE hint1 LIKE 'The word starts with the letter%'" );
	
	if ( empty( $fallbacks ) ) {
		wp_send_json_success( array( 'message' => 'No low-quality fallbacks found.' ) );
	}

	$count = 0;
	foreach ( $fallbacks as $puzzle ) {
		$puzzle_data = (array) $puzzle;
		$word = $puzzle_data['word'];
		
		// Try AI again
		$hints = Wordle_AI::generate_hints( $word );
		
		if ( ! is_wp_error( $hints ) ) {
			$puzzle_data = array_merge( $puzzle_data, $hints );
		} else {
			// Try Advanced Static Fallback
			$puzzle_data = array_merge( $puzzle_data, Wordle_Scraper::generate_fallback_hints( $word ) );
		}
		
		// Remove 'id' and 'created_at' to allow clean update via insert_puzzle logic
		unset($puzzle_data['id']);
		unset($puzzle_data['created_at']);
		
		Wordle_DB::insert_puzzle( $puzzle_data );
		$count++;
	}

	wp_send_json_success( array( 'message' => "Successfully regenerated hints for $count puzzles." ) );
} );

// AJAX handler to test AI
add_action( 'wp_ajax_test_wordle_ai', function() {
	check_ajax_referer( 'wordle_test_ai_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	$test_word = 'APPLE';
	$hints = Wordle_AI::generate_hints( $test_word );

	if ( is_wp_error( $hints ) ) {
		wp_send_json_error( array( 'message' => $hints->get_error_message() ) );
	} else {
		wp_send_json_success( array( 'hints' => $hints ) );
	}
} );

// AJAX handler to test Fallback AI
add_action( 'wp_ajax_test_wordle_fallback_ai', function() {
	check_ajax_referer( 'wordle_test_fallback_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	$test_word = 'GRAPE';
	$hints = Wordle_AI::test_fallback_connection( $test_word );

	if ( is_wp_error( $hints ) ) {
		wp_send_json_error( array( 'message' => $hints->get_error_message() ) );
	} else {
		wp_send_json_success( array( 'hints' => $hints ) );
	}
} );
