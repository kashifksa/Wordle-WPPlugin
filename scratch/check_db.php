<?php
require_once('../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$results = $wpdb->get_results("SELECT date, word, puzzle_number FROM $table ORDER BY date DESC LIMIT 10");

echo "LAST 10 PUZZLES:\n";
foreach ($results as $row) {
    echo $row->date . " | " . $row->word . " (#" . $row->puzzle_number . ")\n";
}
