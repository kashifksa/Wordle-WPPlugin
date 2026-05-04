<?php
// Load WordPress
require_once('../../../../wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

// Check if table exists
if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
    echo "Table $table does not exist.\n";
    exit;
}

$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$has_hints = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE hint1 IS NOT NULL AND hint1 != '' AND hint1 != 'Generating...'");
$missing_hints = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE hint1 IS NULL OR hint1 = '' OR hint1 = 'Generating...'");

echo "Total records in DB: $total\n";
echo "Records WITH hints: $has_hints\n";
echo "Records MISSING hints: $missing_hints\n";

if ($has_hints > 0) {
    $rows = $wpdb->get_results("SELECT puzzle_number, word, date FROM $table WHERE hint1 IS NOT NULL AND hint1 != '' AND hint1 != 'Generating...' ORDER BY puzzle_number DESC LIMIT 5");
    echo "Sample records WITH hints:\n";
    foreach ($rows as $row) {
        echo "#$row->puzzle_number: $row->word ($row->date)\n";
    }
}
