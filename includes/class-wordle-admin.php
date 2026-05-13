<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Simple logging system to track the last 50 events.
	 */
	public static function log( $message, $type = 'info' ) {
		$logs = get_option( 'wordle_hint_logs', array() );
		$new_log = array(
			'time' => current_time( 'mysql' ),
			'msg'  => $message,
			'type' => $type
		);
		array_unshift( $logs, $new_log );
		$logs = array_slice( $logs, 0, 50 ); // Keep only last 50
		update_option( 'wordle_hint_logs', $logs );
	}

	public static function add_admin_menu() {
		add_menu_page(
			'Wordle Hint Pro',
			'Wordle Hint',
			'manage_options',
			'wordle-hint-settings',
			array( __CLASS__, 'unified_admin_page' ),
			'dashicons-lightbulb'
		);
	}

	public static function unified_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'dashboard';
		?>
		<div class="wrap">
			<h1>Wordle Hint Pro</h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=wordle-hint-settings&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
				<a href="?page=wordle-hint-settings&tab=manage" class="nav-tab <?php echo $active_tab == 'manage' ? 'nav-tab-active' : ''; ?>">Manage Puzzles</a>
				<a href="?page=wordle-hint-settings&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">System Logs</a>
				<a href="?page=wordle-hint-settings&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
			</h2>

			<?php
			switch ( $active_tab ) {
				case 'manage':
					self::manage_puzzles_page();
					break;
				case 'logs':
					self::logs_page();
					break;
				case 'settings':
					self::settings_page();
					break;
				case 'dashboard':
				default:
					self::dashboard_page();
					break;
			}
			?>
		</div>
		<?php
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
		register_setting( 'wordle_hint_settings_group', 'wordle_stats_refresh_interval' );
	}

	public static function dashboard_page() {
		?>
		<div class="dashboard-wrapper" style="margin-top: 20px;">
			<h2>System Health (Last 7 Days)</h2>
			<table class="wp-list-table widefat fixed striped" style="max-width: 800px; margin-bottom: 20px;">
				<thead>
					<tr>
						<th style="width: 150px;">Date</th>
						<th style="width: 120px;">Status</th>
						<th style="width: 100px;">Word</th>
						<th>Source</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					global $wpdb;
					$table_name = Wordle_DB::get_table_name();
					for ( $i = 0; $i < 7; $i++ ) : 
						$date = date( 'Y-m-d', strtotime( "-$i days" ) );
						$entry = $wpdb->get_row( $wpdb->prepare( "SELECT entry_source, word FROM $table_name WHERE date = %s", $date ) );
						?>
						<tr>
							<td><strong><?php echo ($i === 0) ? 'Today' : date( 'M j, Y', strtotime( $date ) ); ?></strong></td>
							<td>
								<?php if ( $entry ) : ?>
									<span style="color: #6aaa64; font-weight: bold;">✅ Success</span>
								<?php else : ?>
									<span style="color: #d32f2f; font-weight: bold;">❌ Missing</span>
								<?php endif; ?>
							</td>
							<td><code><?php echo $entry ? esc_html( $entry->word ) : '-----'; ?></code></td>
							<td><?php echo $entry ? ucfirst( esc_html( $entry->entry_source ) ) : '<em style="color: #999;">Not fetched yet</em>'; ?></td>
						</tr>
					<?php endfor; ?>
				</tbody>
			</table>

			<table class="wp-list-table widefat fixed striped" style="max-width: 800px;">
				<tr>
					<td style="width: 150px;"><strong>JSON Cache Status:</strong></td>
					<td>
						<?php 
						$file = WORDLE_HINT_PATH . 'wordle-data.json';
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
			<h2>Quick Actions</h2>
			<div class="action-buttons" style="margin-bottom: 20px;">
				<button id="run-scraper-now" class="button button-primary">Run Scraper Now</button>
				<button id="fetch-save-json" class="button button-primary">Fetch & Save JSON</button>
				<button id="backfill-stats" class="button button-secondary" style="background: #6aaa64; color: white; border-color: #5a9a54;">Backfill WordleBot Stats</button>
				<button id="backfill-dictionary" class="button button-secondary" style="background: #2271b1; color: white; border-color: #135e96;">Backfill Dictionary</button>
				<button id="regenerate-fallbacks" class="button button-secondary">Regenerate Fallbacks</button>
				<button id="test-ai-connection" class="button button-secondary">Test Primary AI</button>
				<button id="test-fallback-ai" class="button button-secondary">Test Fallback AI</button>
			</div>

			<div id="scraper-log" style="margin-top: 10px; padding: 15px; background: #f0f0f0; border: 1px solid #ccc; max-height: 300px; overflow-y: auto; display:none; border-radius: 4px; font-family: monospace;"></div>
			
			<script>
			jQuery(document).ready(function($) {
				// Action Handlers
				$('#run-scraper-now').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Running...');
					$log.show().html('Starting scraper...');
					$.post(ajaxurl, { action: 'run_wordle_scraper', nonce: '<?php echo wp_create_nonce("wordle_scraper_nonce"); ?>' }, function(response) {
						$log.append('<br>' + response.data.message);
						$btn.prop('disabled', false).text('Run Scraper Now');
					});
				});

				$('#fetch-save-json').click(function(e, isManual) {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Refreshing...');
					if (isManual !== false) $log.show().html('Starting JSON cache generation...');
					else $log.append('<br>Starting JSON cache generation...');
					
					$.post({
						url: '<?php echo get_rest_url(null, "wordle/v1/refresh-json"); ?>',
						beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>'); },
						success: function(response) { $log.append('<br>Success: ' + response.message); },
						error: function() { $log.append('<br>Error: Failed to refresh JSON cache'); },
						complete: function() { $btn.prop('disabled', false).text('Fetch & Save JSON'); }
					});
				});

				$('#backfill-stats').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					if (!confirm('Backfill WordleBot difficulty stats?')) return;
					$btn.prop('disabled', true).text('Backfilling...');
					$log.show().html('<strong>Starting WordleBot Stats Backfill...</strong><br>');
					function runStatsBatch() {
						$.post(ajaxurl, { action: 'backfill_wordle_stats', nonce: '<?php echo wp_create_nonce("wordle_stats_nonce"); ?>' }, function(response) {
							if (response.success) {
								$log.append(response.data.message + '<br>');
								if (response.data.remaining > 0) runStatsBatch();
								else {
									$log.append('<strong>✔ Stats backfill complete!</strong>');
									$btn.prop('disabled', false).text('Backfill WordleBot Stats');
									$('#fetch-save-json').click();
								}
							}
						});
					}
					runStatsBatch();
				});

				$('#backfill-dictionary').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					if (!confirm('Backfill Dictionary data from Merriam-Webster?')) return;
					$btn.prop('disabled', true).text('Backfilling...');
					$log.show().html('<strong>Starting Dictionary Enrichment Backfill...</strong><br>');
					function runDictBatch() {
						$.post(ajaxurl, { action: 'backfill_wordle_dictionary', nonce: '<?php echo wp_create_nonce("wordle_dict_nonce"); ?>' }, function(response) {
							if (response.success) {
								$log.append(response.data.message + '<br>');
								if (response.data.remaining > 0) setTimeout(runDictBatch, 1000);
								else {
									$log.append('<strong>✔ Dictionary enrichment complete!</strong>');
									$btn.prop('disabled', false).text('Backfill Dictionary');
									$('#fetch-save-json').trigger('click', [false]);
								}
							}
						});
					}
					runDictBatch();
				});

				$('#regenerate-fallbacks').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Regenerating...');
					$log.show().html('Scanning for low-quality fallbacks...');
					$.post(ajaxurl, { action: 'regenerate_wordle_fallbacks', nonce: '<?php echo wp_create_nonce("wordle_regen_nonce"); ?>' }, function(response) {
						$log.append('<br>' + response.data.message);
						if (response.success) $('#fetch-save-json').click();
						$btn.prop('disabled', false).text('Regenerate Fallbacks');
					});
				});

				$('#test-ai-connection').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Testing...');
					$log.show().html('Testing Primary AI connection...');
					$.post(ajaxurl, { action: 'test_wordle_ai', nonce: '<?php echo wp_create_nonce("wordle_test_ai_nonce"); ?>' }, function(response) {
						if (response.success) $log.append('<br><span style="color:green;">✔ Success!</span> AI hints generated.');
						else $log.append('<br><span style="color:red;">❌ Error:</span> ' + response.data.message);
						$btn.prop('disabled', false).text('Test Primary AI');
					});
				});

				$('#test-fallback-ai').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).text('Testing...');
					$log.show().html('Testing Fallback AI connection...');
					$.post(ajaxurl, { action: 'test_wordle_fallback_ai', nonce: '<?php echo wp_create_nonce("wordle_test_fallback_nonce"); ?>' }, function(response) {
						if (response.success) $log.append('<br><span style="color:green;">✔ Success!</span> AI hints generated.');
						else $log.append('<br><span style="color:red;">❌ Error:</span> ' + response.data.message);
						$btn.prop('disabled', false).text('Test Fallback AI');
					});
				});
			});
			</script>
		</div>
		<?php
	}

	public static function settings_page() {
		?>
		<div class="settings-wrapper" style="margin-top: 20px;">
			<form method="post" action="options.php">
				<?php settings_fields( 'wordle_hint_settings_group' ); ?>
				<?php do_settings_sections( 'wordle_hint_settings_group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Scrape URL</th>
						<td>
							<input type="text" name="wordle_hint_scrape_url" value="<?php echo esc_attr( get_option( 'wordle_hint_scrape_url' ) ); ?>" placeholder="https://www.nytimes.com/svc/wordle/v2/" class="large-text" />
							<p class="description">Default: NYT Wordle v2 endpoint.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Primary AI API Key</th>
						<td><input type="password" name="wordle_hint_ai_api_key" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_api_key' ) ); ?>" class="large-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Primary AI Model</th>
						<td><input type="text" name="wordle_hint_ai_model" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_model', 'llama-3.1-8b-instant' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Fallback AI API Key</th>
						<td><input type="password" name="wordle_hint_ai_api_key_fallback" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_api_key_fallback' ) ); ?>" class="large-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Fallback AI Model</th>
						<td><input type="text" name="wordle_hint_ai_model_fallback" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_model_fallback' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">AI Prompt Template</th>
						<td>
							<textarea name="wordle_hint_ai_prompt" rows="6" class="large-text"><?php echo esc_textarea( get_option( 'wordle_hint_ai_prompt' ) ); ?></textarea>
							<p class="description">Use {{WORD}} as placeholder.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Merriam-Webster Dictionary Key</th>
						<td><input type="password" name="wordle_mw_dictionary_key" value="<?php echo esc_attr( get_option( 'wordle_mw_dictionary_key' ) ); ?>" class="large-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Stats Refresh Interval (Hours)</th>
						<td><input type="number" name="wordle_stats_refresh_interval" value="<?php echo esc_attr( get_option( 'wordle_stats_refresh_interval', 4 ) ); ?>" class="small-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h3>Manual Entry & Data Management</h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Manual Entry</th>
					<td>
						<input type="text" id="manual_wordle_word" maxlength="5" placeholder="WORD" style="text-transform:uppercase; width: 80px;" />
						<input type="number" id="manual_wordle_number" placeholder="No." style="width: 80px;" />
						<input type="date" id="manual_wordle_date" value="<?php echo current_time('Y-m-d'); ?>" />
						<button type="button" id="save-manual-wordle" class="button">Save Entry</button>
						<div id="manual-status-msg"></div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">CSV Archive Upload</th>
					<td>
						<input type="file" id="wordle_csv_file" accept=".csv" />
						<button type="button" id="upload-wordle-csv" class="button">Upload CSV</button>
						<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=wordle-hint-settings&tab=settings&action=wordle_export_csv'), 'wordle_export_nonce' ); ?>" class="button button-secondary" style="margin-left: 10px;">Export All Puzzles (CSV)</a>
						<div id="csv-status-msg"></div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Bulk AI Generation</th>
					<td>
						<select id="batch_ai_engine">
							<option value="default">Default</option>
							<option value="primary">Primary Only</option>
						</select>
						<input type="number" id="batch_size_input" value="10" style="width:60px;" />
						<button id="batch-generate-ai" class="button button-primary">Start Batch</button>
						<button id="stop-batch-ai" class="button" style="display:none;">Stop</button>
					</td>
				</tr>
			</table>

			<script>
			jQuery(document).ready(function($) {
				$('#save-manual-wordle').click(function() {
					var $btn = $(this);
					var $status = $('#manual-status-msg');
					$btn.prop('disabled', true).text('Saving...');
					$.post(ajaxurl, {
						action: 'save_manual_wordle',
						nonce: '<?php echo wp_create_nonce("wordle_manual_nonce"); ?>',
						word: $('#manual_wordle_word').val(),
						number: $('#manual_wordle_number').val(),
						date: $('#manual_wordle_date').val()
					}, function(response) {
						$status.text(response.data.message);
						$btn.prop('disabled', false).text('Save Entry');
					});
				});

				$('#upload-wordle-csv').click(function() {
					var file_data = $('#wordle_csv_file').prop('files')[0];
					if (!file_data) return;
					var form_data = new FormData();
					form_data.append('file', file_data);
					form_data.append('action', 'upload_wordle_csv');
					form_data.append('nonce', '<?php echo wp_create_nonce("wordle_csv_nonce"); ?>');
					$.ajax({
						url: ajaxurl, type: 'POST', data: form_data, contentType: false, processData: false,
						success: function(response) { $('#csv-status-msg').text(response.data.message); }
					});
				});

				var isBatchRunning = false;
				$('#batch-generate-ai').click(function() {
					isBatchRunning = true;
					$('#stop-batch-ai').show();
					function runBatch() {
						if (!isBatchRunning) return;
						$.post(ajaxurl, {
							action: 'batch_generate_ai_hints',
							nonce: '<?php echo wp_create_nonce("wordle_batch_ai_nonce"); ?>',
							batch_size: $('#batch_size_input').val(),
							engine: $('#batch_ai_engine').val()
						}, function(response) {
							if (response.data.remaining > 0) runBatch();
							else $('#stop-batch-ai').hide();
						});
					}
					runBatch();
				});
				$('#stop-batch-ai').click(function() { isBatchRunning = false; $(this).hide(); });
			});
			</script>
		</div>
		<?php
	}

	public static function manage_puzzles_page() {
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-list-table.php';
		$list_table = new Wordle_List_Table();
		$list_table->prepare_items();
		?>
		<div class="manage-wrapper" style="margin-top: 20px;">
			<form method="post">
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function logs_page() {
		$logs = get_option( 'wordle_hint_logs', array() );
		?>
		<div class="logs-wrapper" style="margin-top: 20px;">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 180px;">Timestamp</th>
						<th style="width: 100px;">Type</th>
						<th>Event Message</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="3">No logs recorded yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><code><?php echo esc_html( $log['time'] ); ?></code></td>
								<td>
									<span class="status-badge" style="padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; background: <?php echo $log['type'] === 'error' ? '#f8d7da' : '#d1ecf1'; ?>; color: <?php echo $log['type'] === 'error' ? '#721c24' : '#0c5460'; ?>;">
										<?php echo esc_html( $log['type'] ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log['msg'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Handle CSV Export request
	 */
	public static function handle_export() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'wordle_export_csv' ) {
			return;
		}

		check_admin_referer( 'wordle_export_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = Wordle_DB::get_table_name();
		$results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY date DESC", ARRAY_A );

		if ( empty( $results ) ) {
			wp_die( 'No data to export.' );
		}

		$filename = 'wordle-export-' . date('Y-m-d') . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		// Add header row
		fputcsv( $output, array_keys( $results[0] ) );

		// Add data rows
		foreach ( $results as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		
		self::log( "User exported entire database to CSV ($filename)", 'info' );
		exit;
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
		if ( $stats && ! is_wp_error( $stats ) ) {
			$wpdb->update( $table, $stats, array( 'puzzle_number' => $p->puzzle_number ) );
			$count++;
		}
		usleep( 200000 ); // 200ms pause
	}

	if ( $count > 0 && class_exists( 'Wordle_API' ) ) {
		Wordle_API::refresh_json_cache();
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

	if ( $count > 0 && class_exists( 'Wordle_API' ) ) {
		Wordle_API::refresh_json_cache();
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
