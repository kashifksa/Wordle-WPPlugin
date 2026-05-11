<?php
require_once(__DIR__ . '/../../../../wp-load.php');

$word = 'apple';
$dict_key = get_option( 'wordle_mw_dictionary_key' );
$url = "https://www.dictionaryapi.com/api/v3/references/collegiate/json/{$word}?key={$dict_key}";

echo "Testing URL: $url\n";
$response = wp_remote_get( $url );

if ( is_wp_error( $response ) ) {
    die("WP_Error: " . $response->get_error_message());
}

$code = wp_remote_retrieve_response_code( $response );
$body = wp_remote_retrieve_body( $response );

echo "Response Code: $code\n";
echo "Response Body: " . substr($body, 0, 500) . "...\n";

$json = json_decode( $body, true );
if ( is_array( $json ) ) {
    echo "JSON is array. First item type: " . gettype($json[0]) . "\n";
} else {
    echo "JSON is NOT array.\n";
}
