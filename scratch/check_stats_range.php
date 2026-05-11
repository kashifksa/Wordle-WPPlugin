<?php
require_once(__DIR__ . '/../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$min = $wpdb->get_var("SELECT MIN(puzzle_number) FROM $table WHERE puzzle_number > 0");
$max = $wpdb->get_var("SELECT MAX(puzzle_number) FROM $table WHERE puzzle_number > 0");
$total_missing = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE (difficulty IS NULL OR difficulty = 0) AND puzzle_number > 0");
echo "DB range: $min to $max\n";
echo "Missing stats: $total_missing\n";

$sample = $wpdb->get_results("SELECT puzzle_number FROM $table WHERE (difficulty IS NULL OR difficulty = 0) AND puzzle_number > 419 LIMIT 5");
echo "Sample missing ( > 419): ";
foreach($sample as $s) echo $s->puzzle_number . " ";
echo "\n";
