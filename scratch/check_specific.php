<?php
require_once('../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE puzzle_number = 1774"));
if ($row) {
    echo "Puzzle #1774 ($row->word):\n";
    echo "Hint 1: " . ($row->hint1 ?: "NULL") . "\n";
} else {
    echo "Puzzle #1774 not found in DB.\n";
}
