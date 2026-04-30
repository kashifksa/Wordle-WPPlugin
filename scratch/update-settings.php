<?php
/**
 * Update the fallback model setting.
 */
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../../../../wp-load.php');

update_option('wordle_hint_ai_model_fallback', 'gemini-2.0-flash');
echo "Updated fallback model to gemini-2.0-flash\n";
