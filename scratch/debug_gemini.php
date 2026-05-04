<?php
require_once('../../../../wp-load.php');
require_once('../includes/class-wordle-ai.php');

$word = 'GRIEF';
$key = get_option('wordle_hint_ai_api_key_fallback');
$model = get_option('wordle_hint_ai_model_fallback');

echo "Testing Gemini Connection...\n";
echo "Word: $word\n";
echo "Model: $model\n";
echo "Key: " . substr($key, 0, 5) . "...\n";

$result = Wordle_AI::generate_hints($word, 'fallback');

if (is_wp_error($result)) {
    echo "ERROR: " . $result->get_error_message() . "\n";
    if ($result->get_error_data()) {
        print_r($result->get_error_data());
    }
} else {
    echo "SUCCESS:\n";
    print_r($result);
}
