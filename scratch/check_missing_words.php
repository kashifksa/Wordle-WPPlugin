<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';

$csv_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle_dataset_enriched.csv';
$csv_words = [];
if ( file_exists( $csv_file ) ) {
    if ( ( $handle = fopen( $csv_file, "r" ) ) !== FALSE ) {
        fgetcsv( $handle ); // Skip header
        while ( ( $data = fgetcsv( $handle ) ) !== FALSE ) {
            if ( isset( $data[2] ) ) {
                $word = strtoupper( trim( $data[2] ) );
                if ( strlen( $word ) === 5 ) {
                    $csv_words[] = $word;
                }
            }
        }
        fclose( $handle );
    }
}

global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$db_words = $wpdb->get_col( "SELECT word FROM $table" );
$db_words = array_map(function($w) { return strtoupper(trim($w)); }, $db_words);
$db_words = array_filter($db_words, function($w) { return strlen($w) === 5; });

$all_words = array_unique(array_merge($csv_words, $db_words));
sort($all_words);

$json_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle-solver-data.json';
$json_data = json_decode(file_get_contents($json_file), true);
$json_words = $json_data['words'];

$missing_from_json = array_diff($all_words, $json_words);

echo "Total unique words in CSV + DB: " . count($all_words) . "\n";
echo "Total words in JSON: " . count($json_words) . "\n";
echo "Missing from JSON: " . count($missing_from_json) . "\n";

if (!empty($missing_from_json)) {
    echo "Examples: " . implode(', ', array_slice($missing_from_json, 0, 10)) . "\n";
} else {
    echo "All CSV/DB words are present in the JSON.\n";
}
