<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE definition IS NULL OR definition = '' OR synonyms IS NULL OR example_sentence IS NULL");
echo "Total Missing: $count\n";

$rows = $wpdb->get_results("SELECT word FROM $table WHERE definition IS NULL OR definition = '' OR synonyms IS NULL OR example_sentence IS NULL LIMIT 10");
foreach($rows as $r) echo $r->word . ", ";
