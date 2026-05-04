<?php
require_once('../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE puzzle_number = 699"));
if ($row) {
    echo "Puzzle #699 ($row->word):\n";
    echo "Hint 1: " . ($row->hint1 ?: "NULL") . "\n";
} else {
    echo "Puzzle #699 not found in DB.\n";
}
