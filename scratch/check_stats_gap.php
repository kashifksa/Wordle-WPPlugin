<?php
require_once(__DIR__ . '/../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$sample = $wpdb->get_results("SELECT puzzle_number FROM $table WHERE (difficulty IS NULL OR difficulty = 0) AND puzzle_number BETWEEN 419 AND 437");
if (empty($sample)) {
    echo "None of 419-437 are missing stats in DB.\n";
} else {
    echo "Puzzles missing stats in range 419-437: ";
    foreach($sample as $s) echo $s->puzzle_number . " ";
    echo "\n";
}
