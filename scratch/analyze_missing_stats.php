<?php
require_once( 'c:/xampp/htdocs/TodayWordle/wp-load.php' );
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$has_data_range = $wpdb->get_row("SELECT MIN(date) as min_date, MAX(date) as max_date FROM $table WHERE difficulty IS NOT NULL AND difficulty > 0", ARRAY_A);
$missing_data_range = $wpdb->get_row("SELECT MIN(date) as min_date, MAX(date) as max_date FROM $table WHERE difficulty IS NULL OR difficulty = 0", ARRAY_A);

echo "Records WITH data range: {$has_data_range['min_date']} to {$has_data_range['max_date']}\n";
echo "Records MISSING data range: {$missing_data_range['min_date']} to {$missing_data_range['max_date']}\n";

$first_missing = $wpdb->get_results("SELECT puzzle_number, date, word FROM $table WHERE difficulty IS NULL OR difficulty = 0 ORDER BY date LIMIT 10", ARRAY_A);
echo "\nFirst 10 missing:\n";
print_r($first_missing);

$last_missing = $wpdb->get_results("SELECT puzzle_number, date, word FROM $table WHERE difficulty IS NULL OR difficulty = 0 ORDER BY date DESC LIMIT 10", ARRAY_A);
echo "\nLast 10 missing:\n";
print_r($last_missing);
