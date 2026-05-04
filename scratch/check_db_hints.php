<?php
require_once('../../../../wp-load.php');
global $wpdb;
$table = 'wp_wordle_data';

$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$has_hints = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE hint1 IS NOT NULL AND hint1 != '' AND hint1 != 'Generating...'");
$missing = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE hint1 IS NULL OR hint1 = '' OR hint1 = 'Generating...'");

echo "Total Records: $total\n";
echo "With Hints: $has_hints\n";
echo "Missing Hints: $missing\n";

echo "\nLatest 10 records with hints:\n";
$results = $wpdb->get_results("SELECT puzzle_number, word, hint1 FROM $table WHERE hint1 IS NOT NULL AND hint1 != '' AND hint1 != 'Generating...' ORDER BY puzzle_number DESC LIMIT 10");
foreach ($results as $r) {
    echo "#" . $r->puzzle_number . " (" . $r->word . "): " . substr($r->hint1, 0, 50) . "...\n";
}

echo "\nFirst 10 records missing hints:\n";
$results = $wpdb->get_results("SELECT puzzle_number, word FROM $table WHERE hint1 IS NULL OR hint1 = '' OR hint1 = 'Generating...' ORDER BY puzzle_number ASC LIMIT 10");
foreach ($results as $r) {
    echo "#" . $r->puzzle_number . " (" . $r->word . ")\n";
}
