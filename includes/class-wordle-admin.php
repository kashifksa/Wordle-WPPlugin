<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_wordle-hint-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'wh-lucide-icons', 'https://unpkg.com/lucide@latest', array(), null, true );
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
		<style>
			.wh-spin { animation: wh-rotate 2s linear infinite; }
			@keyframes wh-rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
			#scraper-log strong { color: #2271b1; }
			#scraper-log span { font-weight: 600; }
			
			/* Standardized Button UI */
			.wh-btn { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; padding: 8px 20px !important; transition: all 0.2s ease !important; border-radius: 8px !important; font-weight: 600 !important; min-width: 140px !important; text-align: center !important; cursor: pointer !important; position: relative !important; overflow: hidden !important; }
			.wh-btn i, .wh-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
			.wh-btn i:empty, .wh-badge i:empty { display: none !important; } /* Fix shift when icon missing */
			.wh-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3); }
			.wh-btn:active { transform: translateY(0); }
			
			.wh-btn-primary, .wh-btn-success, .wh-btn-purple { background: #2271b1 !important; border: 1px solid #2271b1 !important; color: #fff !important; }
			.wh-btn-primary:hover, .wh-btn-success:hover, .wh-btn-purple:hover { background: #135e96 !important; border-color: #135e96 !important; }
			
			.wh-btn-secondary { background: #fff !important; border: 1px solid #ccd0d4 !important; color: #2c3338 !important; }
			.wh-btn-secondary:hover { background: #f6f7f7 !important; border-color: #c3c4c7 !important; }
			
			/* Status Badges */
			.wh-badge { padding: 4px 10px !important; border-radius: 6px !important; font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 4px !important; }
			.wh-badge-success { background: #e7f4e4 !important; color: #1e4620 !important; border: 1px solid #c3e6cb !important; }
			.wh-badge-info { background: #e3f2fd !important; color: #0d47a1 !important; border: 1px solid #bbdefb !important; }
			.wh-badge-error { background: #fdecea !important; color: #b71c1c !important; border: 1px solid #f5c6cb !important; }
			.wh-badge-warning { background: #fff8e1 !important; color: #ff6f00 !important; border: 1px solid #ffecb3 !important; }
			
			/* Global WP Button Overrides for Unified UI */
			.wrap .wp-core-ui .button:not(.wh-btn) { border-radius: 8px !important; padding: 4px 12px !important; height: auto !important; line-height: 1.6 !important; transition: all 0.2s ease !important; }
			.wrap .wp-core-ui .button-primary:not(.wh-btn) { background: #2271b1 !important; border-color: #2271b1 !important; }
			.wrap .wp-core-ui .button:hover:not(.wh-btn) { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
			.wrap .search-box input[type="submit"], .wrap .bulkactions input[type="submit"], .wrap .tablenav .button { border-radius: 8px !important; }
			
			/* Input Styling */
			.wrap input[type="text"], .wrap input[type="number"], .wrap input[type="date"], .wrap select { border-radius: 8px !important; border: 1px solid #ccd0d4 !important; padding: 6px 12px !important; height: 38px !important; vertical-align: middle !important; box-shadow: none !important; }
			.wrap input:focus, .wrap select:focus { border-color: #2271b1 !important; box-shadow: 0 0 0 1px #2271b1 !important; }
		</style>
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

			<!-- Shared Progress Log -->
			<div id="scraper-log" style="margin-top: 20px; padding: 20px; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; display:none; border-radius: 12px; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; box-shadow: 0 10px 30px rgba(0,0,0,0.05); line-height: 1.6; color: #1d2327; font-size: 13px;"></div>
		</div>
		<?php
	}

	public static function register_settings() {
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_scrape_url' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_api_key' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_model' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_api_key_fallback' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_model_fallback' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_system_prompt' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_ai_prompt' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_api_key' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_timezone' );
		register_setting( 'wordle_hint_settings_group', 'wordle_hint_cron_schedule' );
		register_setting( 'wordle_hint_settings_group', 'wordle_mw_dictionary_key' );
		register_setting( 'wordle_hint_settings_group', 'wordle_mw_thesaurus_key' );
		register_setting( 'wordle_hint_settings_group', 'wordle_stats_refresh_interval' );
		
		// Network & Multi-Site Settings
		register_setting( 'wordle_hint_settings_group', 'wordle_operation_mode' ); // master or client
		register_setting( 'wordle_hint_settings_group', 'wordle_network_sharing_key' ); // Key for this site to share data
		register_setting( 'wordle_hint_settings_group', 'wordle_master_api_url' ); // Hub URL for client mode
		register_setting( 'wordle_hint_settings_group', 'wordle_master_api_key' ); // Key to access hub
		register_setting( 'wordle_hint_settings_group', 'wordle_master_api_url_fallback' ); // Fallback hub
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
									<span class="wh-badge wh-badge-success"><i data-lucide="check-circle" style="width:12px; height:12px;"></i> Success</span>
								<?php else : ?>
									<span class="wh-badge wh-badge-error"><i data-lucide="x-circle" style="width:12px; height:12px;"></i> Missing</span>
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
			<div class="action-buttons" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 25px;">
				<?php if ( get_option( 'wordle_operation_mode', 'master' ) === 'master' ) : ?>
					<button id="run-scraper-now" class="button wh-btn wh-btn-primary"><i data-lucide="refresh-cw"></i> Run Scraper Now</button>
				<?php else : ?>
					<button id="run-sync-now" class="button wh-btn wh-btn-primary"><i data-lucide="arrow-down-circle"></i> Sync with Master Hub</button>
				<?php endif; ?>
				<button id="fetch-save-json" class="button wh-btn wh-btn-primary"><i data-lucide="file-json"></i> Fetch & Save JSON</button>
				<button id="backfill-stats" class="button wh-btn wh-btn-primary"><i data-lucide="bar-chart-2"></i> Backfill Stats</button>
				<button id="backfill-dictionary" class="button wh-btn wh-btn-primary"><i data-lucide="book-open"></i> Backfill Dictionary</button>
				<button id="regenerate-fallbacks" class="button wh-btn wh-btn-primary"><i data-lucide="wand-2"></i> Regenerate Fallbacks</button>
				<button id="test-ai-connection" class="button wh-btn wh-btn-primary"><i data-lucide="zap"></i> Test Primary AI</button>
				<button id="test-fallback-ai" class="button wh-btn wh-btn-primary"><i data-lucide="shield-check"></i> Test Fallback AI</button>
			</div>
			
			<script>
			jQuery(document).ready(function($) {
				// Action Handlers
				$('#run-scraper-now').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).html('<i data-lucide="loader-2" class="wh-spin"></i> Running...');
					if (window.lucide) window.lucide.createIcons();
					$log.show().html('Starting scraper...');
					$.post(ajaxurl, { action: 'run_wordle_scraper', nonce: '<?php echo wp_create_nonce("wordle_scraper_nonce"); ?>' }, function(response) {
						$log.append('<br>' + response.data.message);
						$btn.prop('disabled', false).html('<i data-lucide="refresh-cw"></i> Run Scraper Now');
						if (typeof lucide !== 'undefined') lucide.createIcons();
					});
				});

				$('#run-sync-now').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).html('<i data-lucide="loader-2" class="wh-spin"></i> Syncing...');
					if (typeof lucide !== 'undefined') lucide.createIcons();
					$log.show().html('Connecting to Master Hub...');
					$.post(ajaxurl, { action: 'run_wordle_sync', nonce: '<?php echo wp_create_nonce("wordle_sync_nonce"); ?>' }, function(response) {
						$log.append('<br>' + response.data.message);
						$btn.prop('disabled', false).html('<i data-lucide="arrow-down-circle"></i> Sync with Master Hub');
						if (window.lucide) window.lucide.createIcons();
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
						complete: function() { 
							$btn.prop('disabled', false).html('<i data-lucide="file-json"></i> Fetch & Save JSON');
							if (typeof lucide !== 'undefined') lucide.createIcons();
						}
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
									$btn.prop('disabled', false).html('<i data-lucide="bar-chart-2"></i> Backfill WordleBot Stats');
									if (window.lucide) window.lucide.createIcons();
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
					$btn.prop('disabled', true).html('<i data-lucide="loader-2" class="wh-spin"></i> Backfilling...');
					if (window.lucide) window.lucide.createIcons();
					$log.show().html('<strong>Starting Dictionary Enrichment Backfill...</strong><br>');
					function runDictBatch() {
						$.post(ajaxurl, { action: 'backfill_wordle_dictionary', nonce: '<?php echo wp_create_nonce("wordle_dict_nonce"); ?>' }, function(response) {
							if (response.success) {
								$log.append(response.data.message + '<br>');
								if (response.data.remaining > 0) setTimeout(runDictBatch, 1000);
								else {
									$log.append('<strong>✔ Dictionary enrichment complete!</strong>');
									$btn.prop('disabled', false).html('<i data-lucide="book-open"></i> Backfill Dictionary');
									if (window.lucide) window.lucide.createIcons();
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
					$btn.prop('disabled', true).html('<i data-lucide="loader-2" class="wh-spin"></i> Regenerating...');
					if (window.lucide) window.lucide.createIcons();
					$log.show().html('Scanning for low-quality fallbacks...');
					$.post(ajaxurl, { action: 'regenerate_wordle_fallbacks', nonce: '<?php echo wp_create_nonce("wordle_regen_nonce"); ?>' }, function(response) {
						$log.append('<br>' + response.data.message);
						if (response.success) $('#fetch-save-json').click();
						$btn.prop('disabled', false).html('<i data-lucide="wand-2"></i> Regenerate Fallbacks');
						if (window.lucide) window.lucide.createIcons();
					});
				});

				$('#test-ai-connection').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).html('<i data-lucide="loader-2" class="wh-spin"></i> Testing...');
					if (window.lucide) window.lucide.createIcons();
					$log.show().html('Testing Primary AI connection...');
					$.post(ajaxurl, { action: 'test_wordle_ai', nonce: '<?php echo wp_create_nonce("wordle_test_ai_nonce"); ?>' }, function(response) {
						if (response.success) $log.append('<br><span style="color:green;">✔ Success!</span> AI hints generated.');
						else $log.append('<br><span style="color:red;">❌ Error:</span> ' + response.data.message);
						$btn.prop('disabled', false).html('<i data-lucide="zap"></i> Test Primary AI');
						if (window.lucide) window.lucide.createIcons();
					});
				});

				$('#test-fallback-ai').click(function() {
					var $btn = $(this);
					var $log = $('#scraper-log');
					$btn.prop('disabled', true).html('<i data-lucide="loader-2" class="wh-spin"></i> Testing...');
					if (window.lucide) window.lucide.createIcons();
					$log.show().html('Testing Fallback AI connection...');
					$.post(ajaxurl, { action: 'test_wordle_fallback_ai', nonce: '<?php echo wp_create_nonce("wordle_test_fallback_nonce"); ?>' }, function(response) {
						if (response.success) $log.append('<br><span style="color:green;">✔ Success!</span> AI hints generated.');
						else $log.append('<br><span style="color:red;">❌ Error:</span> ' + response.data.message);
						$btn.prop('disabled', false).html('<i data-lucide="shield-check"></i> Test Fallback AI');
						if (window.lucide) window.lucide.createIcons();
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
						<th scope="row">AI System Persona (Personality)</th>
						<td>
							<textarea name="wordle_hint_ai_system_prompt" rows="4" class="large-text" placeholder="e.g. You are a mysterious riddler who speaks in metaphors..."><?php echo esc_textarea( get_option( 'wordle_hint_ai_system_prompt' ) ); ?></textarea>
							<p class="description">Define the AI's personality here. This ensures each site in your network generates unique hints.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">AI Prompt Template</th>
						<td>
							<textarea name="wordle_hint_ai_prompt" rows="6" class="large-text"><?php echo esc_textarea( get_option( 'wordle_hint_ai_prompt' ) ); ?></textarea>
							<p class="description">Use {{WORD}} as placeholder for the main instructions.</p>
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

				<hr>
				<h3>Network & Multi-Site (Step 26/27)</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Operation Mode</th>
						<td>
							<select name="wordle_operation_mode">
								<option value="master" <?php selected( get_option( 'wordle_operation_mode', 'master' ), 'master' ); ?>>Master Hub (Main Site)</option>
								<option value="client" <?php selected( get_option( 'wordle_operation_mode' ), 'client' ); ?>>Client Satellite (Child Site)</option>
							</select>
							<p class="description">Master scrapes NYT; Client pulls data from your Master Hub.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Master Sharing Key</th>
						<td>
							<input type="password" name="wordle_network_sharing_key" value="<?php echo esc_attr( get_option( 'wordle_network_sharing_key' ) ); ?>" class="large-text" />
							<p class="description">Set a secret key here if this is your Master site. Child sites must use this key to fetch your data.</p>
						</td>
					</tr>
					<tr valign="top" class="wh-client-only" <?php echo get_option('wordle_operation_mode') === 'client' ? '' : 'style="display:none;"'; ?>>
						<th scope="row">Master API URL</th>
						<td>
							<div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-start;">
								<input type="text" id="wordle_master_api_url" name="wordle_master_api_url" value="<?php echo esc_attr( get_option( 'wordle_master_api_url' ) ); ?>" class="large-text" placeholder="https://mainsite.com/wp-json/wordle/v1" />
								<div style="display: flex; align-items: center; gap: 12px;">
									<button type="button" id="test-master-connection" class="button wh-btn wh-btn-secondary"><i data-lucide="link"></i> Test Hub Connection</button>
									<span id="connection-test-result" style="font-weight: bold;"></span>
								</div>
							</div>
						</td>
					</tr>
					<tr valign="top" class="wh-client-only" <?php echo get_option('wordle_operation_mode') === 'client' ? '' : 'style="display:none;"'; ?>>
						<th scope="row">Master API Key</th>
						<td>
							<input type="password" name="wordle_master_api_key" value="<?php echo esc_attr( get_option( 'wordle_master_api_key' ) ); ?>" class="large-text" />
							<p class="description">The "Master Sharing Key" from your Hub site.</p>
						</td>
					</tr>
					<tr valign="top" class="wh-client-only" <?php echo get_option('wordle_operation_mode') === 'client' ? '' : 'style="display:none;"'; ?>>
						<th scope="row">Fallback Master API URL</th>
						<td>
							<input type="text" name="wordle_master_api_url_fallback" value="<?php echo esc_attr( get_option( 'wordle_master_api_url_fallback' ) ); ?>" class="large-text" placeholder="https://backup-mainsite.com/wp-json/wordle/v1" />
						</td>
					</tr>
				</table>

				<script>
				jQuery(document).ready(function($) {
					$('select[name="wordle_operation_mode"]').change(function() {
						if ($(this).val() === 'client') {
							$('.wh-client-only').fadeIn();
						} else {
							$('.wh-client-only').fadeOut();
						}
					});

					$('#test-master-connection').click(function() {
						var $btn = $(this);
						var $result = $('#connection-test-result');
						var masterUrl = $('#wordle_master_api_url').val();
						var masterKey = $('input[name="wordle_master_api_key"]').val();

						if (!masterUrl || !masterKey) {
							$result.html('<span style="color:red;">❌ Please enter both URL and Key first!</span>');
							return;
						}

						$btn.prop('disabled', true).text('Testing...');
						$result.html('⏳ Connecting...');

						$.post(ajaxurl, {
							action: 'test_master_connection',
							nonce: '<?php echo wp_create_nonce("wordle_sync_nonce"); ?>',
							url: masterUrl,
							key: masterKey
						}, function(response) {
							if (response.success) {
								$result.html('<span style="color:#6aaa64;">✅ Connected Successfully!</span>');
							} else {
								$result.html('<span style="color:red;">❌ Failed: ' + response.data.message + '</span>');
							}
							$btn.prop('disabled', false).text('Test Hub Connection');
						});
					});
				});
				</script>
				<p class="submit">
					<button type="submit" name="submit" id="submit" class="button wh-btn wh-btn-primary" style="padding: 10px 24px !important; font-size: 14px !important;">
						<i data-lucide="save"></i> Save All Settings
					</button>
				</p>
			</form>

			<hr>
			<h3>Manual Entry & Data Management</h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Manual Entry</th>
					<td>
						<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
							<input type="text" id="manual_wordle_word" maxlength="5" placeholder="WORD" style="text-transform:uppercase; width: 100px;" />
							<input type="number" id="manual_wordle_number" placeholder="No." style="width: 80px;" />
							<input type="date" id="manual_wordle_date" value="<?php echo current_time('Y-m-d'); ?>" />
							<button type="button" id="save-manual-wordle" class="button wh-btn wh-btn-primary"><i data-lucide="save"></i> Save Entry</button>
						</div>
						<div id="manual-status-msg" style="margin-top: 5px; font-size: 12px; font-style: italic;"></div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">CSV Archive Upload</th>
					<td>
						<div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
							<input type="file" id="wordle_csv_file" accept=".csv" />
							<button type="button" id="upload-wordle-csv" class="button wh-btn wh-btn-primary"><i data-lucide="upload"></i> Upload CSV</button>
							<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=wordle-hint-settings&tab=settings&action=wordle_export_csv'), 'wordle_export_nonce' ); ?>" class="button wh-btn wh-btn-secondary"><i data-lucide="download"></i> Export Full</a>
							<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=wordle-hint-settings&tab=settings&action=wordle_export_csv&clean=1'), 'wordle_export_nonce' ); ?>" class="button wh-btn wh-btn-secondary" title="Export without hints - ideal for importing into new child sites"><i data-lucide="download-cloud"></i> Export Clean</a>
						</div>
						<div id="csv-status-msg"></div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Bulk AI Generation</th>
					<td>
						<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
							<select id="batch_ai_engine">
								<option value="default">Default</option>
								<option value="primary">Primary Only</option>
							</select>
							<input type="number" id="batch_size_input" value="10" style="width:70px;" />
							<button id="batch-generate-ai" class="button wh-btn wh-btn-primary">
								<i data-lucide="play-circle"></i> Start Batch
							</button>
							<button id="stop-batch-ai" class="button wh-btn wh-btn-secondary" style="display:none; color: #d63638; border-color: #d63638;">
								<i data-lucide="stop-circle"></i> Stop
							</button>
						</div>
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
					var $btn = $(this);
					var $log = $('#scraper-log');
					isBatchRunning = true;
					$btn.prop('disabled', true).html('<i data-lucide="loader-2" style="width: 16px; height: 16px;" class="wh-spin"></i> Running Batch...');
					if (typeof lucide !== 'undefined') lucide.createIcons();
					$('#stop-batch-ai').css('display', 'inline-flex');
					$log.show().html('<strong>Starting AI Batch Generation...</strong><br>');
					
					function runBatch() {
						if (!isBatchRunning) {
							$log.append('<br><span style="color:orange;">⚠ Batch stopped by user.</span>');
							$btn.prop('disabled', false).html('<i data-lucide="play-circle" style="width: 16px; height: 16px;"></i> Start Batch');
							if (typeof lucide !== 'undefined') lucide.createIcons();
							return;
						}
						
						$.post(ajaxurl, {
							action: 'batch_generate_ai_hints',
							nonce: '<?php echo wp_create_nonce("wordle_batch_ai_nonce"); ?>',
							batch_size: $('#batch_size_input').val(),
							engine: $('#batch_ai_engine').val()
						}, function(response) {
							if (response.success) {
								$log.append(response.data.message + '<br>');
								if (response.data.remaining > 0 && !response.data.paused) {
									runBatch();
								} else {
									isBatchRunning = false;
									$('#stop-batch-ai').hide();
									$btn.prop('disabled', false).html('<i data-lucide="play-circle" style="width: 16px; height: 16px;"></i> Start Batch');
									if (typeof lucide !== 'undefined') lucide.createIcons();
									if (!response.data.paused) {
										$log.append('<strong>✔ All missing hints generated!</strong>');
										$('#fetch-save-json').trigger('click', [false]);
									}
								}
							} else {
								$log.append('<br><span style="color:red;">❌ Error: ' + response.data.message + '</span>');
								isBatchRunning = false;
								$('#stop-batch-ai').hide();
								$btn.prop('disabled', false).html('<i data-lucide="play-circle" style="width: 16px; height: 16px;"></i> Start Batch');
								if (typeof lucide !== 'undefined') lucide.createIcons();
							}
						}).fail(function() {
							$log.append('<br><span style="color:red;">❌ Fatal Error: Server communication failed.</span>');
							isBatchRunning = false;
							$('#stop-batch-ai').hide();
							$btn.prop('disabled', false).html('<i data-lucide="play-circle" style="width: 16px; height: 16px;"></i> Start Batch');
							if (typeof lucide !== 'undefined') lucide.createIcons();
						});
					}
					runBatch();
				});
				$('#stop-batch-ai').click(function() { 
					isBatchRunning = false; 
					$(this).hide(); 
					$('#batch-generate-ai').prop('disabled', false).html('<i data-lucide="play-circle" style="width: 16px; height: 16px;"></i> Start Batch');
					if (typeof lucide !== 'undefined') lucide.createIcons();
				});

				// Initialize Icons
				if (window.lucide) window.lucide.createIcons();
				else {
					// Fallback if script loads late
					setTimeout(function() { if(window.lucide) window.lucide.createIcons(); }, 1000);
					setTimeout(function() { if(window.lucide) window.lucide.createIcons(); }, 3000);
				}
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
		$mode = get_option( 'wordle_operation_mode', 'master' );
		?>
		<div class="logs-wrapper" style="margin-top: 20px;">
			<!-- Network Status Header -->
			<div class="wh-network-status" style="display: flex; gap: 20px; margin-bottom: 25px; background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #ccd0d4; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
				<div class="wh-status-card">
					<span style="display: block; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 700; margin-bottom: 5px;">Operation Mode</span>
					<strong style="font-size: 16px; color: #1d2327;"><?php echo $mode === 'master' ? '🚀 Master Hub' : '📡 Client Satellite'; ?></strong>
				</div>
				<div class="wh-status-card" style="border-left: 1px solid #dcdcde; padding-left: 20px;">
					<span style="display: block; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 700; margin-bottom: 5px;">Audit Status</span>
					<strong style="font-size: 16px; color: <?php echo count($logs) > 40 ? '#d63638' : '#2271b1'; ?>;"><?php echo count($logs); ?> Events Recorded</strong>
				</div>
				<?php if ( $mode === 'client' ) : ?>
					<div class="wh-status-card" style="border-left: 1px solid #dcdcde; padding-left: 20px;">
						<span style="display: block; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 700; margin-bottom: 5px;">Hub Connection</span>
						<span class="wh-badge wh-badge-success"><i data-lucide="shield-check" style="width:12px; height:12px;"></i> Connected</span>
					</div>
				<?php endif; ?>
				<div style="margin-left: auto; align-self: center;">
					<button id="clear-logs-btn" class="button wh-btn wh-btn-secondary" style="color: #d63638; border-color: #d63638;"><i data-lucide="trash-2"></i> Clear All Logs</button>
				</div>
			</div>

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
									<?php 
									$badge_class = 'wh-badge-info';
									$icon = 'info';
									if ($log['type'] === 'error') { $badge_class = 'wh-badge-error'; $icon = 'alert-circle'; }
									if ($log['type'] === 'warning') { $badge_class = 'wh-badge-warning'; $icon = 'alert-triangle'; }
									?>
									<span class="wh-badge <?php echo $badge_class; ?>">
										<i data-lucide="<?php echo $icon; ?>" style="width:12px; height:12px;"></i>
										<?php echo esc_html( $log['type'] ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log['msg'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<script>
			jQuery(document).ready(function($) {
				$('#clear-logs-btn').click(function() {
					if (!confirm('Are you sure you want to permanently delete all system logs?')) return;
					$.post(ajaxurl, { action: 'clear_wordle_logs', nonce: '<?php echo wp_create_nonce("wordle_logs_nonce"); ?>' }, function() {
						location.reload();
					});
				});
			});
			</script>
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

		$is_clean = isset( $_GET['clean'] ) && $_GET['clean'] === '1';
		$filename = $is_clean ? 'wordle-clean-archive-' : 'wordle-export-';
		$filename .= date('Y-m-d') . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		
		// Add UTF-8 BOM for Excel compatibility
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		// Define columns to exclude in clean mode
		$exclude = array( 'hint1', 'hint2', 'hint3', 'final_hint', 'definition', 'synonyms', 'antonyms', 'example_sentence', 'etymology', 'definitions_json' );

		// Get headers
		$headers = array_keys( $results[0] );
		if ( $is_clean ) {
			$headers = array_diff( $headers, $exclude );
		}
		
		fputcsv( $output, $headers );

		// Add data rows
		foreach ( $results as $row ) {
			if ( $is_clean ) {
				foreach ( $exclude as $col ) {
					unset( $row[$col] );
				}
			}
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
		// Remove BOM and all non-printable characters more aggressively
		$col = strtolower(trim(preg_replace('/[^[:print:]]/', '', $col)));
		// If still empty or weird, try a fallback clean
		if (empty($col)) $col = strtolower(trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $header[$index])));
		
		if ( $col === 'date' ) $map['date'] = $index;
		if ( in_array( $col, array( 'wordle_number', 'puzzle_number', 'number', 'puzzlenumber' ) ) ) $map['puzzle_number'] = $index;
		if ( in_array( $col, array( 'answer', 'word' ) ) ) $map['word'] = $index;
		if ( in_array( $col, array( 'vowel_count', 'vowels' ) ) ) $map['vowel_count'] = $index;
		if ( in_array( $col, array( 'consonant_count', 'consonants' ) ) ) $map['consonant_count'] = $index;
		if ( in_array( $col, array( 'repeated_letters', 'repeated' ) ) ) $map['repeated_letters'] = $index;
		if ( in_array( $col, array( 'first_letter', 'starts_with' ) ) ) $map['first_letter'] = $index;
		if ( in_array( $col, array( 'difficulty' ) ) ) $map['difficulty'] = $index;
		if ( in_array( $col, array( 'average_guesses', 'avg_guesses' ) ) ) $map['average_guesses'] = $index;
	}

	// Validate minimal mapping
	if ( ! isset( $map['date'] ) || ! isset( $map['puzzle_number'] ) || ! isset( $map['word'] ) ) {
		fclose( $handle );
		$found_headers = implode( ', ', $header );
		wp_send_json_error( array( 'message' => 'CSV missing required columns (Date, Number, Word). Found: ' . $found_headers ) );
	}

	$count_inserted = 0;
	$count_updated = 0;
	$errors = 0;

	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		if ( count( $row ) < 3 ) continue;

		$date = sanitize_text_field( $row[$map['date']] );
		$puzzle_number = intval( $row[$map['puzzle_number']] );
		$word = strtoupper( sanitize_text_field( $row[$map['word']] ) );

		if ( ! $date || ! $puzzle_number || strlen( $word ) !== 5 ) {
			$errors++;
			continue;
		}

		$data = array(
			'date'          => $date,
			'puzzle_number' => $puzzle_number,
			'word'          => $word,
			'entry_source'  => 'csv_import'
		);

		// Optional fields
		if ( isset( $map['vowel_count'] ) ) $data['vowel_count'] = intval( $row[$map['vowel_count']] );
		if ( isset( $map['consonant_count'] ) ) $data['consonant_count'] = intval( $row[$map['consonant_count']] );
		if ( isset( $map['repeated_letters'] ) ) $data['repeated_letters'] = intval( $row[$map['repeated_letters']] );
		if ( isset( $map['first_letter'] ) ) $data['first_letter'] = sanitize_text_field( $row[$map['first_letter']] );
		if ( isset( $map['difficulty'] ) ) $data['difficulty'] = sanitize_text_field( $row[$map['difficulty']] );
		if ( isset( $map['average_guesses'] ) ) $data['average_guesses'] = floatval( $row[$map['average_guesses']] );

		// If missing analysis, run it
		if ( ! isset( $data['vowel_count'] ) ) {
			$analysis = Wordle_Scraper::analyze_word( $word );
			$data = array_merge( $data, $analysis );
		}

		// Save
		$result = Wordle_DB::insert_puzzle( $data );
		if ( $result !== false ) $count_inserted++;
		else $errors++;
	}

	fclose( $handle );

	wp_send_json_success( array( 
		'message' => "Archive upload complete! New records: $count_inserted | Updated: $count_updated | Failed: $errors."
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

	// Load the stats from engagingData.js (which should be in the plugin root)
	$stats_file = WORDLE_HINT_PATH . 'engagingData.js';
	if ( ! file_exists( $stats_file ) ) {
		wp_send_json_error( array( 'message' => 'Stats file not found: ' . $stats_file ) );
	}

	$content = file_get_contents( $stats_file );
	
	// Parse the JS array into PHP
	// The file contains: var engagingData = [ ... ];
	// We need everything between [ and ]
	preg_match( '/\[.*\]/s', $content, $matches );
	if ( empty( $matches ) ) {
		wp_send_json_error( array( 'message' => 'Could not parse stats data' ) );
	}

	// Clean up for JSON parsing (remove trailing commas, etc.)
	$json_str = $matches[0];
	// Basic cleanup for non-standard JS objects
	$json_str = preg_replace( '/(\w+):/', '"$1":', $json_str ); // wrap keys in quotes
	$json_str = str_replace( "'", '"', $json_str ); // replace single quotes with double

	$stats_data = json_decode( $json_str, true );
	if ( ! $stats_data ) {
		wp_send_json_error( array( 'message' => 'JSON decoding failed' ) );
	}

	// Find records missing difficulty stats
	$missing = $wpdb->get_results( "SELECT id, puzzle_number FROM $table WHERE difficulty IS NULL OR difficulty = '' LIMIT 20" );

	if ( empty( $missing ) ) {
		wp_send_json_success( array( 'message' => 'No missing stats found.', 'remaining' => 0 ) );
	}

	$count = 0;
	foreach ( $missing as $record ) {
		foreach ( $stats_data as $stat ) {
			if ( intval( $stat['number'] ) === intval( $record->puzzle_number ) ) {
				$wpdb->update( $table, array(
					'difficulty'      => sanitize_text_field( $stat['difficulty'] ),
					'average_guesses' => floatval( $stat['avgGuesses'] )
				), array( 'id' => $record->id ) );
				$count++;
				break;
			}
		}
	}

	$remaining = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE difficulty IS NULL OR difficulty = ''" );

	wp_send_json_success( array( 
		'message' => "Updated $count puzzles. $remaining still missing stats.",
		'remaining' => $remaining
	) );
} );

// AJAX handler to backfill dictionary
add_action( 'wp_ajax_backfill_wordle_dictionary', function() {
	check_ajax_referer( 'wordle_dict_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	global $wpdb;
	$table = Wordle_DB::get_table_name();

	// Find records missing definition
	$missing = $wpdb->get_results( "SELECT id, word FROM $table WHERE definition IS NULL OR definition = '' LIMIT 5" );

	if ( empty( $missing ) ) {
		wp_send_json_success( array( 'message' => 'No missing dictionary data found.', 'remaining' => 0 ) );
	}

	$count = 0;
	foreach ( $missing as $record ) {
		$dictionary = Wordle_Dictionary::fetch_enrichment( $record->word );
		if ( ! is_wp_error( $dictionary ) ) {
			$wpdb->update( $table, $dictionary, array( 'id' => $record->id ) );
			$count++;
		}
		// Brief sleep to be polite to APIs
		usleep( 500000 );
	}

	$remaining = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE definition IS NULL OR definition = ''" );

	wp_send_json_success( array( 
		'message' => "Enriched $count words. $remaining still missing dictionary data.",
		'remaining' => $remaining
	) );
} );

// AJAX handler to test Hub connection
add_action( 'wp_ajax_test_master_connection', function() {
	check_ajax_referer( 'wordle_sync_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	$url = trailingslashit( sanitize_text_field( $_POST['url'] ) ) . 'latest-wordle';
	$key = sanitize_text_field( $_POST['key'] );

	$response = wp_remote_get( $url, array(
		'headers' => array( 'X-Wordle-Key' => $key ),
		'timeout' => 15
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => $response->get_error_message() ) );
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code !== 200 ) {
		wp_send_json_error( array( 'message' => "HTTP Error $code" ) );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! $body || ! isset( $body['word'] ) ) {
		wp_send_json_error( array( 'message' => "Invalid response format" ) );
	}

	wp_send_json_success( array( 'message' => 'Connection successful!' ) );
} );

// AJAX handler for manual sync
add_action( 'wp_ajax_run_wordle_sync', function() {
	check_ajax_referer( 'wordle_sync_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	$result = Wordle_Sync::sync();
	
	if ( $result ) {
		wp_send_json_success( array( 'message' => 'Success: Site synchronized with Master Hub.' ) );
	} else {
		wp_send_json_error( array( 'message' => 'Failed: Sync error. Check System Logs for details.' ) );
	}
} );

// AJAX handler to clear logs
add_action( 'wp_ajax_clear_wordle_logs', function() {
	check_ajax_referer( 'wordle_logs_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	update_option( 'wordle_hint_logs', array() );
	wp_send_json_success();
} );
