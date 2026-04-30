<?php
/**
 * Diagnostic script to list available Gemini models.
 * Run this via CLI: php wp-content/plugins/WordleHintPro/scratch/list-models.php
 */

// Load WordPress environment
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../../../../wp-load.php');

$api_key = get_option('wordle_hint_ai_api_key_fallback');

if (empty($api_key)) {
    die("Error: No Gemini API Key found in WordPress options.\n");
}

echo "Probing available models for your API key...\n";

$versions = ['v1', 'v1beta'];
foreach ($versions as $version) {
    echo "\nChecking API Version: $version\n";
    $url = "https://generativelanguage.googleapis.com/$version/models?key=$api_key";
    
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        echo "HTTP Error: " . $response->get_error_message() . "\n";
        continue;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status = wp_remote_retrieve_response_code($response);
    
    if ($status !== 200) {
        echo "API Error ($status): " . ($body['error']['message'] ?? 'Unknown error') . "\n";
        continue;
    }
    
    if (empty($body['models'])) {
        echo "No models returned for this version.\n";
        continue;
    }
    
    foreach ($body['models'] as $model) {
        $name = str_replace('models/', '', $model['name']);
        $display = $model['displayName'] ?? 'No Name';
        echo "- $name ($display)\n";
    }
}
