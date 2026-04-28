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
					<tr valign="top">
						<th scope="row">AI API Key (OpenAI/Gemini)</th>
						<td><input type="password" name="wordle_hint_ai_api_key" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_api_key' ) ); ?>" class="large-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">AI Model</th>
						<td><input type="text" name="wordle_hint_ai_model" value="<?php echo esc_attr( get_option( 'wordle_hint_ai_model', 'gpt-3.5-turbo' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">AI Prompt Template</th>
						<td><textarea name="wordle_hint_ai_prompt" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'wordle_hint_ai_prompt', 'Generate 4 Wordle hints for the word {{WORD}}. Hint 1: vague, Hint 2: category, Hint 3: specific, Hint 4: final strong hint.' ) ); ?></textarea></td>
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
			<h2>Actions</h2>
			<button id="run-scraper-now" class="button button-primary">Run Scraper Now</button>
			<button id="fetch-save-json" class="button button-primary">Fetch & Save JSON</button>
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
					$btn.prop('disabled', true).text('Fetching...');
					$log.show().html('Starting JSON fetch...');
					
					// Step 1: GET today's data
					$.get('<?php echo get_rest_url(null, "wordle/v1/today"); ?>', function(data) {
						// Step 2 & 3: POST to save-json
						$.ajax({
							url: '<?php echo get_rest_url(null, "wordle/v1/save-json"); ?>',
							method: 'POST',
							beforeSend: function(xhr) {
								xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
							},
							data: JSON.stringify(data),
							contentType: 'application/json',
							success: function(response) {
								$log.append('<br>Success: ' + response.message);
							},
							error: function() {
								$log.append('<br>Error: Failed to fetch or save JSON');
							},
							complete: function() {
								$btn.prop('disabled', false).text('Fetch & Save JSON');
							}
						});
					}).fail(function() {
						$log.append('<br>Error: Failed to fetch today\'s data. Make sure the scraper has run.');
						$btn.prop('disabled', false).text('Fetch & Save JSON');
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
