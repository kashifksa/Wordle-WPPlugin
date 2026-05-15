<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$words = $wpdb->get_results("SELECT puzzle_number, word FROM $table", ARRAY_A);

$count = count($words);
$unique_words = array_unique(array_column($words, 'word'));
$unique_numbers = array_unique(array_column($words, 'puzzle_number'));

echo "Total rows: $count\n";
echo "Unique words: " . count($unique_words) . "\n";
echo "Unique puzzle numbers: " . count($unique_numbers) . "\n";

$max_num = max($unique_numbers);
$min_num = min($unique_numbers);
echo "Range: $min_num to $max_num\n";

// Count how many are exactly 5 letters
$five_letters = array_filter($unique_words, function($w) { return strlen(trim($w)) === 5; });
echo "Five letter unique words: " . count($five_letters) . "\n";
