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
		register_setting( 'wordle_hint_settings_group', 'wordle_mw_dictionary_key' );
		register_setting( 'wordle_hint_settings_group', 'wordle_mw_thesaurus_key' );
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
					<tr valign="top" style="background: #fff; border-bottom: 1px solid #ddd;">
						<th scope="row"><h3>Bulk CSV Upload (Archive)</h3></th>
						<td>
							<div class="csv-upload-container" style="padding: 10px 0;">
								<input type="file" id="wordle_csv_file" accept=".csv" />
								<button type="button" id="upload-wordle-csv" class="button button-secondary">Upload CSV Archive</button>
								<p class="description">Upload a CSV with headers: Date, Wordle_Number, Answer, Vowel_Count, Consonant_Count, Repeated_Letters, First_Letter</p>
								<div id="csv-status-msg" style="margin-top: 10px; font-weight: bold;"></div>
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
					<tr valign="top" style="border-top: 1px solid #ccc;">
						<th scope="row">Merriam-Webster Dictionary Key</th>
						<td>
							<input type="password" name="wordle_mw_dictionary_key" value="<?php echo esc_attr( get_option( 'wordle_mw_dictionary_key' ) ); ?>" class="large-text" />
							<p class="description">Required for definitions, pronunciation, and etymology. Get a key at <a href="https://dictionaryapi.com/" target="_blank">dictionaryapi.com</a> (Collegiate Dictionary).</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Merriam-Webster Thesaurus Key</th>
						<td>
							<input type="password" name="wordle_mw_thesaurus_key" value="<?php echo esc_attr( get_option( 'wordle_mw_thesaurus_key' ) ); ?>" class="large-text" />
							<p class="description">Optional. Used for synonyms and antonyms (Collegiate Thesaurus).</p>
						</td>
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
			<button id="backfill-stats" class="button button-secondary" style="background: #6aaa64; color: white; border-color: #5a9a54;">Backfill Missing WordleBot Stats</button>
			<button id="backfill-dictionary" class="button button-secondary" style="background: #2271b1; color: white; border-color: #135e96;">Backfill Dictionary Enrichment</button>
			<button id="regenerate-fallbacks" class="button button-secondary">Regenerate Fallback Hints</button>
			<button id="test-ai-connection" class="button button-secondary">Test Primary AI</button>
			<button id="test-fallback-ai" class="button button-secondary">Test Fallback AI</button>
			<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0; border-radius: 4px; display: block; max-width: 800px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<h4 style="margin-top: 0; margin-bottom: 10px; color: #1d2327;">Bulk AI Hint Generator</h4>
				<span class="description" style="margin-right: 5px;">AI Engine:</span>
				<select id="batch_ai_engine" style="vertical-align: middle; margin-right: 15px;">
					<option value="default">Default (Primary + Fallback)</option>
					<option value="primary">Primary AI Only</option>
					<option value="fallback">Fallback AI Only</option>
				</select>
				
				<span class="description" style="margin-right: 5px;">Batch Size:</span>
				<input type="number" id="batch_size_input" value="10" min="1" max="100" style="width: 70px; height: 30px; vertical-align: middle; margin-right: 15px;" title="Records per batch" />
				
				<button id="batch-generate-ai" class="button button-primary" style="background: #2271b1; border-color: #2271b1;">Batch Generate AI Hints (Archive)</button>
				<button id="stop-batch-ai" class="button button-link-delete" style="display:none; vertical-align: middle; margin-left: 10px;">Stop Generation</button>
				<p class="description" style="margin-top: 10px; margin-bottom: 0;">Processes records missing hints in chunks. Recommended: 10-20 for stability.</p>
			</div>
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

				$('#fetch-save-json').click(function(e, isManual) {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Refreshing...');
					
					if (isManual !== false) {
						$log.show().html('Starting JSON cache generation...');
					} else {
						$log.append('<br>Starting JSON cache generation...');
					}
					
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

				$('#backfill-stats').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					
					if (!confirm('This will fetch WordleBot difficulty stats for all records missing them. Proceed?')) return;
					
					$btn.prop('disabled', true).text('Backfilling Stats...');
					$log.show().html('<strong>Starting WordleBot Stats Backfill...</strong><br>');
					
					function runStatsBatch() {
						$.post(ajaxurl, {
							action: 'backfill_wordle_stats',
							nonce: '<?php echo wp_create_nonce("wordle_stats_nonce"); ?>'
						}, function(response) {
							if (response.success) {
								$log.append(response.data.message + '<br>');
								if (response.data.remaining > 0) {
									runStatsBatch(); // Recursive call
								} else {
									$log.append('<strong>✔ Stats backfill complete!</strong>');
									$btn.prop('disabled', false).text('Backfill Missing WordleBot Stats');
									$('#fetch-save-json').click(); // Refresh JSON
								}
							} else {
								$log.append('<br><span style="color:red;">Error:</span> ' + response.data.message);
								$btn.prop('disabled', false).text('Backfill Missing WordleBot Stats');
							}
						});
					}
					
					runStatsBatch();
				});
				
				$('#backfill-dictionary').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					
					if (!confirm('This will fetch Dictionary & Thesaurus data from Merriam-Webster for all records missing definitions. Proceed?')) return;
					
					$btn.prop('disabled', true).text('Backfilling Dictionary...');
					$log.show().html('<strong>Starting Dictionary Enrichment Backfill...</strong><br>');
					
					function runDictBatch() {
						$.post(ajaxurl, {
							action: 'backfill_wordle_dictionary',
							nonce: '<?php echo wp_create_nonce("wordle_dict_nonce"); ?>'
						}, function(response) {
							if (response.success) {
								$log.append(response.data.message + '<br>');
								if (response.data.remaining > 0) {
									setTimeout(runDictBatch, 1000); // 1s pause between batches
								} else {
									$log.append('<strong>✔ Dictionary enrichment complete!</strong>');
									$btn.prop('disabled', false).text('Backfill Dictionary Enrichment');
									$('#fetch-save-json').trigger('click', [false]); // Refresh JSON without clearing log
								}
							} else {
								$log.append('<br><span style="color:red;">Error:</span> ' + response.data.message);
								$btn.prop('disabled', false).text('Backfill Dictionary Enrichment');
							}
						});
					}
					
					runDictBatch();
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

				$('#upload-wordle-csv').click(function() {
					var file_data = $('#wordle_csv_file').prop('files')[0];
					if (!file_data) {
						alert('Please select a CSV file.');
						return;
					}

					var $btn = $(this);
					var $status = $('#csv-status-msg');
					var form_data = new FormData();
					form_data.append('file', file_data);
					form_data.append('action', 'upload_wordle_csv');
					form_data.append('nonce', '<?php echo wp_create_nonce("wordle_csv_nonce"); ?>');

					$btn.prop('disabled', true).text('Uploading...');
					$status.css('color', 'blue').text('Processing CSV... This may take a moment.');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: form_data,
						contentType: false,
						processData: false,
						success: function(response) {
							if (response.success) {
								$status.css('color', 'green').text('✔ ' + response.data.message);
								$('#fetch-save-json').click(); // Refresh JSON too
							} else {
								$status.css('color', 'red').text('❌ ' + response.data.message);
							}
							$btn.prop('disabled', false).text('Upload CSV Archive');
						},
						error: function() {
							$status.css('color', 'red').text('❌ Upload failed.');
							$btn.prop('disabled', false).text('Upload CSV Archive');
						}
					});
				});

				// Batch AI Generation
				var isBatchRunning = false;

				$('#stop-batch-ai').click(function() {
					isBatchRunning = false;
					$(this).hide();
					$('#batch-generate-ai').prop('disabled', false).text('Batch Generate AI Hints (Archive)');
					$('#scraper-log').append('<span style="color:red;"><strong>✔ Generation stopped by user.</strong></span><br>');
				});

				$('#batch-generate-ai').click(function() {
					if (!confirm('This will call the AI for all records missing hints. Depending on your archive size, this may take a while. Continue?')) return;
					
					isBatchRunning = true;
					var $btn = $(this);
					var $log = $('#scraper-log');
					var $stopBtn = $('#stop-batch-ai');

					$btn.prop('disabled', true).text('Processing Batch...');
					$stopBtn.show();
					$log.show().html('<strong>Starting Batch AI Generation...</strong><br>');

					function processNextBatch() {
						if (!isBatchRunning) return;

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'batch_generate_ai_hints',
								nonce: '<?php echo wp_create_nonce("wordle_batch_ai_nonce"); ?>',
								batch_size: $('#batch_size_input').val(),
								engine: $('#batch_ai_engine').val()
							},
							success: function(response) {
								if (!isBatchRunning) return;

								if (response.success) {
									$log.append(response.data.message + '<br>');
									$log.scrollTop($log[0].scrollHeight);
									
									if (response.data.remaining > 0) {
										if (response.data.paused) {
											$log.append('<span style="color:orange;">⚠ Batch paused to avoid rate limits. Resuming in 30 seconds...</span><br>');
											setTimeout(processNextBatch, 30000); // 30s delay if paused
										} else if (parseInt(response.data.processed) === 0 && parseInt(response.data.errors) > 0) {
											$log.append('<span style="color:red;">❌ Multiple failures. Stopping to prevent loop. Please check your API keys or quota.</span><br>');
											$btn.prop('disabled', false).text('Batch Generate AI Hints (Archive)');
											$stopBtn.hide();
										} else {
											processNextBatch(); // Recursively call for next batch
										}
									} else {
										$log.append('<strong>✔ All AI hints generated successfully!</strong>');
										$btn.prop('disabled', false).text('Batch Generate AI Hints (Archive)');
										$stopBtn.hide();
										$('#fetch-save-json').click(); // Final cache refresh
									}
								} else {
									$log.append('<span style="color:red;">❌ Error: ' + response.data.message + '</span><br>');
									$btn.prop('disabled', false).text('Batch Generate AI Hints (Archive)');
									$stopBtn.hide();
								}
							},
							error: function() {
								if (!isBatchRunning) return;
								$log.append('<span style="color:red;">❌ Connection error. Retrying in 5 seconds...</span><br>');
								setTimeout(processNextBatch, 5000);
							}
						});
					}

					processNextBatch();
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

	// Enrichment: Merriam-Webster Dictionary
	$dictionary = Wordle_Dictionary::fetch_enrichment( $word );
	if ( ! is_wp_error( $dictionary ) ) {
		$data = array_merge( $data, $dictionary );
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

// AJAX handler for CSV upload
add_action( 'wp_ajax_upload_wordle_csv', function() {
	check_ajax_referer( 'wordle_csv_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	if ( empty( $_FILES['file']['tmp_name'] ) ) {
		wp_send_json_error( array( 'message' => 'No file uploaded' ) );
	}

	$file = $_FILES['file']['tmp_name'];
	$handle = fopen( $file, 'r' );
	
	if ( ! $handle ) {
		wp_send_json_error( array( 'message' => 'Cannot open file' ) );
	}

	// Read header
	$header = fgetcsv( $handle );
	if ( ! $header ) {
		fclose( $handle );
		wp_send_json_error( array( 'message' => 'Empty CSV' ) );
	}

	// Map headers to DB columns
	// Flexible mapping for different CSV formats
	$map = array();
	foreach ( $header as $index => $col ) {
		// Remove BOM and hidden characters
		$col = strtolower(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', trim($col)));
		
		if ( $col === 'date' ) $map['date'] = $index;
		if ( in_array( $col, array( 'wordle_number', 'puzzle_number', 'number', 'puzzlenumber' ) ) ) $map['puzzle_number'] = $index;
		if ( in_array( $col, array( 'answer', 'word' ) ) ) $map['word'] = $index;
		if ( in_array( $col, array( 'vowel_count', 'vowels' ) ) ) $map['vowel_count'] = $index;
		if ( in_array( $col, array( 'consonant_count', 'consonants' ) ) ) $map['consonant_count'] = $index;
		if ( in_array( $col, array( 'repeated_letters', 'repeated' ) ) ) $map['repeated_letters'] = $index;
		if ( in_array( $col, array( 'first_letter', 'starts_with' ) ) ) $map['first_letter'] = $index;
	}

	// Validate minimal mapping
	if ( ! isset( $map['date'] ) || ! isset( $map['puzzle_number'] ) || ! isset( $map['word'] ) ) {
		fclose( $handle );
		$found_headers = implode( ', ', $header );
		wp_send_json_error( array( 'message' => "Missing required headers. Found: [$found_headers]. Expected: Date, Wordle_Number, Answer" ) );
	}

	$count_inserted = 0;
	$count_updated = 0;
	$errors = 0;

	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		if ( empty( $row ) || !isset($row[ $map['word'] ]) || empty( $row[ $map['word'] ] ) ) continue;

		$data = array(
			'date'          => date('Y-m-d', strtotime(sanitize_text_field( $row[ $map['date'] ] ))),
			'puzzle_number' => intval( $row[ $map['puzzle_number'] ] ),
			'word'          => strtoupper( sanitize_text_field( $row[ $map['word'] ] ) ),
			'entry_source'  => 'csv_archive',
		);

		// Add optional fields if they exist in CSV
		if ( isset( $map['vowel_count'] ) ) $data['vowel_count'] = intval( $row[ $map['vowel_count'] ] );
		if ( isset( $map['consonant_count'] ) ) $data['consonant_count'] = intval( $row[ $map['consonant_count'] ] );
		if ( isset( $map['repeated_letters'] ) ) $data['repeated_letters'] = sanitize_text_field( $row[ $map['repeated_letters'] ] );
		if ( isset( $map['first_letter'] ) ) $data['first_letter'] = sanitize_text_field( $row[ $map['first_letter'] ] );

		// Check if exists to track insert vs update
		global $wpdb;
		$table = Wordle_DB::get_table_name();
		$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE puzzle_number = %d OR date = %s", $data['puzzle_number'], $data['date']));

		$result = Wordle_DB::insert_puzzle( $data );
		
		if ( $result !== false ) {
			if ($exists) $count_updated++;
			else $count_inserted++;
		} else {
			$errors++;
		}
	}

	fclose( $handle );

	wp_send_json_success( array( 
		'message' => "Archive upload complete! New records: $count_inserted | Updated: $count_updated."
	) );
} );

// AJAX handler for Batch AI Hint Generation
add_action( 'wp_ajax_batch_generate_ai_hints', function() {
	check_ajax_referer( 'wordle_batch_ai_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	global $wpdb;
	$table = Wordle_DB::get_table_name();
	
	// How many to process per request (default 5, max 100)
	$batch_size = isset($_POST['batch_size']) ? min(100, max(1, intval($_POST['batch_size']))) : 5; 

	// Find records where hints are missing (hint1 is usually the indicator)
	$missing = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, word, puzzle_number FROM $table WHERE hint1 IS NULL OR hint1 = '' OR hint1 = 'Generating...' LIMIT %d",
		$batch_size
	) );

	if ( empty( $missing ) ) {
		wp_send_json_success( array( 
			'message'   => 'No records missing hints.', 
			'remaining' => 0 
		) );
	}

	// Get total remaining for progress reporting
	$total_missing = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE hint1 IS NULL OR hint1 = '' OR hint1 = 'Generating...'" );

	$processed = 0;
	$errors = 0;
	$consecutive_errors = 0;
	$engine = isset($_POST['engine']) ? sanitize_text_field($_POST['engine']) : 'default';

	foreach ( $missing as $record ) {
		// Small delay to prevent hitting rate limits too fast (especially for Gemini)
		if ( $processed > 0 || $errors > 0 ) {
			sleep( 2 ); 
		}

		$hints = Wordle_AI::generate_hints( $record->word, $engine );

		if ( ! is_wp_error( $hints ) ) {
			$wpdb->update( $table, $hints, array( 'id' => $record->id ) );
			$processed++;
			$consecutive_errors = 0;
		} else {
			$errors++;
			$consecutive_errors++;
			error_log( "Wordle Batch AI Error for #{$record->puzzle_number} ({$record->word}): " . $hints->get_error_message() );
			
			// If we hit 3 consecutive errors (likely rate limit or API down), stop this batch early
			if ( $consecutive_errors >= 3 ) {
				wp_send_json_success( array( 
					'message'   => "Batch paused due to consecutive errors: " . $hints->get_error_message() . ". ($total_missing remaining)", 
					'remaining' => $total_missing - $processed,
					'errors'    => $errors,
					'paused'    => true
				) );
			}
		}
	}

	wp_send_json_success( array( 
		'message'   => "Processed $processed words. ($total_missing remaining)", 
		'remaining' => $total_missing - $processed,
		'errors'    => $errors
	) );
} );

// AJAX handler to backfill stats
add_action( 'wp_ajax_backfill_wordle_stats', function() {
	check_ajax_referer( 'wordle_stats_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	global $wpdb;
	$table = Wordle_DB::get_table_name();
	$today = current_time( 'Y-m-d' );
	
	// Find entries missing difficulty or distribution (Only past puzzles with valid numbers)
	$missing = $wpdb->get_results( $wpdb->prepare(
		"SELECT puzzle_number FROM $table WHERE (difficulty IS NULL OR difficulty = 0 OR guess_distribution IS NULL OR guess_distribution = '') AND date <= %s AND puzzle_number > 0 LIMIT 20",
		$today
	) );
	
	if ( empty( $missing ) ) {
		wp_send_json_success( array( 'message' => 'No puzzles missing stats.', 'remaining' => 0 ) );
	}

	$total_missing = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $table WHERE (difficulty IS NULL OR difficulty = 0 OR guess_distribution IS NULL OR guess_distribution = '') AND date <= %s AND puzzle_number > 0",
		$today
	) );
	
	$count = 0;
	foreach ( $missing as $p ) {
		$stats = Wordle_Scraper::fetch_wordlebot_stats( $p->puzzle_number );
		if ( $stats ) {
			$wpdb->update( $table, $stats, array( 'puzzle_number' => $p->puzzle_number ) );
			$count++;
		}
		usleep( 200000 ); // 200ms pause
	}

	wp_send_json_success( array( 
		'message'   => "Updated stats for $count puzzles. (" . ( $total_missing - $count ) . " remaining)", 
		'remaining' => $total_missing - $count 
	) );
} );

// AJAX handler to backfill dictionary data
add_action( 'wp_ajax_backfill_wordle_dictionary', function() {
	check_ajax_referer( 'wordle_dict_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	global $wpdb;
	$table = Wordle_DB::get_table_name();

	// Ensure the database schema is up to date
	Wordle_DB::create_table();

	// Check for API key first
	$dict_key = Wordle_Dictionary::sanitize_key( get_option( 'wordle_mw_dictionary_key' ) );
	if ( empty( $dict_key ) ) {
		wp_send_json_error( array( 'message' => 'Merriam-Webster Dictionary API key is missing. Please enter it in settings.' ) );
	}
	
	// Find entries missing critical metadata (including antonyms and pronunciation)
	$missing = $wpdb->get_results( "SELECT id, word FROM $table WHERE 
		definition IS NULL OR definition = '' OR 
		synonyms IS NULL OR synonyms = '' OR 
		antonyms IS NULL OR antonyms = '' OR 
		example_sentence IS NULL OR example_sentence = '' OR 
		etymology IS NULL OR etymology = '' OR
		first_known_use LIKE '%{%' OR etymology LIKE '%{%'
		LIMIT 10" );
	
	if ( empty( $missing ) ) {
		wp_send_json_success( array( 'message' => 'No puzzles missing dictionary data.', 'remaining' => 0 ) );
	}

	$total_missing = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE 
		definition IS NULL OR definition = '' OR 
		synonyms IS NULL OR synonyms = '' OR 
		antonyms IS NULL OR antonyms = '' OR 
		example_sentence IS NULL OR example_sentence = '' OR 
		etymology IS NULL OR etymology = '' OR
		first_known_use LIKE '%{%' OR etymology LIKE '%{%'" );
	
	$count = 0;
	$details = array();
	foreach ( $missing as $record ) {
		$sources = array();
		
		// Tier 1: Merriam-Webster (Primary)
		$enrichment = Wordle_Dictionary::fetch_enrichment( $record->word );
		if ( is_wp_error( $enrichment ) ) {
			$errors[] = $record->word . ': MW Error - ' . $enrichment->get_error_message();
			$enrichment = array();
		} else {
			if ( ! empty( $enrichment ) ) $sources[] = 'MW';
		}

		// Tier 2: Free Dictionary API (Backup)
		if ( empty( $enrichment['definition'] ) || empty( $enrichment['synonyms'] ) || $enrichment['synonyms'] === '[]' ) {
			$free_data = Wordle_Dictionary::fetch_free_dictionary_enrichment( $record->word );
			if ( ! is_wp_error( $free_data ) ) {
				$sources[] = 'FreeAPI';
				foreach ( $free_data as $key => $val ) {
					if ( empty( $enrichment[ $key ] ) || $enrichment[ $key ] === '[]' ) {
						$enrichment[ $key ] = $val;
					}
				}
			}
		}

		// Tier 3: AI Enrichment (Final Gap-Fill)
		$has_gaps = empty( $enrichment['definition'] ) || empty( $enrichment['synonyms'] ) || $enrichment['synonyms'] === '[]' || empty( $enrichment['antonyms'] ) || $enrichment['antonyms'] === '[]' || empty( $enrichment['example_sentence'] );
		if ( $has_gaps ) {
			$enrichment = Wordle_AI::enrich_dictionary_data( $record->word, $enrichment );
			$sources[] = 'AI';
		}
		
		// Final fallback placeholders to prevent infinite loops
		if ( empty( $enrichment['definition'] ) ) $enrichment['definition'] = 'Definition unavailable';
		if ( empty( $enrichment['synonyms'] ) )   $enrichment['synonyms'] = '[]';
		if ( empty( $enrichment['antonyms'] ) )   $enrichment['antonyms'] = '[]';
		if ( empty( $enrichment['etymology'] ) )  $enrichment['etymology'] = 'Etymology unavailable';
		if ( empty( $enrichment['example_sentence'] ) ) $enrichment['example_sentence'] = 'Example unavailable';

		$wpdb->update( $table, $enrichment, array( 'id' => $record->id ) );
		$count++;
		
		$details[] = $record->word . ' (' . implode( '+', array_unique( $sources ) ) . ')';
		
		sleep( 1 ); // Rate limit protection
	}

	$message = "Enriched $count puzzles: " . implode( ', ', $details ) . ". (" . ( $total_missing - $count ) . " remaining)";
	if ( ! empty( $errors ) ) {
		$message .= "<br><span style='color:red;'>Errors:</span><br>" . implode( "<br>", $errors );
	}

	wp_send_json_success( array( 
		'message'   => $message, 
		'remaining' => $total_missing - $count 
	) );
} );
