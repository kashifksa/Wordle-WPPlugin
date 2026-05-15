<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$puzzles = $wpdb->get_col("SELECT puzzle_number FROM $table ORDER BY puzzle_number ASC");

$max = 1791; // Today's puzzle number approx
$missing_numbers = [];
for ($i = 0; $i <= $max; $i++) {
    if (!in_array($i, $puzzles)) {
        $missing_numbers[] = $i;
    }
}

echo "Total missing puzzle numbers: " . count($missing_numbers) . "\n";
echo "Missing: " . implode(', ', $missing_numbers) . "\n";
