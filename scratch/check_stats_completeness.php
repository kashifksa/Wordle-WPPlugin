<?php
require_once( 'c:/xampp/htdocs/TodayWordle/wp-load.php' );
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$missing_difficulty = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE difficulty IS NULL OR difficulty = 0");
$missing_avg = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE average_guesses IS NULL OR average_guesses = 0");
$missing_dist = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE guess_distribution IS NULL OR guess_distribution = ''");

echo "Total Records: $total\n";
echo "Missing Difficulty: $missing_difficulty\n";
echo "Missing Average Guesses: $missing_avg\n";
echo "Missing Distribution: $missing_dist\n";

$sample = $wpdb->get_results("SELECT puzzle_number, date, word, difficulty FROM $table WHERE difficulty IS NOT NULL AND difficulty > 0 LIMIT 5", ARRAY_A);
echo "\nSample with data:\n";
print_r($sample);
