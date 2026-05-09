<?php
require_once( 'c:/xampp/htdocs/TodayWordle/wp-load.php' );
global $wpdb;

$options = [
    'wordle_client_main_json_url',
    'wordle_client_detected_json_url',
    'wordle_hint_scrape_url'
];

foreach ($options as $opt) {
    echo "Option: $opt | Value: " . get_option($opt) . "\n";
}
