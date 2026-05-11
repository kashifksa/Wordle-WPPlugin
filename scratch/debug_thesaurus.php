<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
require_once 'includes/class-wordle-dictionary.php';

$word = "PARKA";
$thes_key = Wordle_Dictionary::sanitize_key( get_option( 'wordle_mw_thesaurus_key' ) );
echo "Thesaurus Key: $thes_key\n";

$url = "https://www.dictionaryapi.com/api/v3/references/thesaurus/json/" . strtolower($word) . "?key=" . $thes_key;
echo "URL: $url\n";

$response = wp_remote_get( $url );
if ( is_wp_error( $response ) ) {
    die("Error: " . $response->get_error_message());
}

$body = wp_remote_retrieve_body( $response );
echo "Body: " . substr($body, 0, 500) . "...\n";

$json = json_decode( $body, true );
if ( ! is_array( $json ) ) {
    die("Invalid JSON");
}

if ( empty( $json ) || ! is_array( $json[0] ) ) {
    echo "Word not found in Thesaurus or suggestions returned.\n";
    print_r($json);
} else {
    $data = Wordle_Dictionary::fetch_enrichment($word);
    echo "Parsed Data:\n";
    print_r($data);
}
