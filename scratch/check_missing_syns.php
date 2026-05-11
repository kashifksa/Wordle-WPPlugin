<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$rows = $wpdb->get_results("SELECT word FROM $table WHERE num_definitions > 0 AND synonyms IS NULL LIMIT 10");
foreach($rows as $r) {
    echo $r->word . PHP_EOL;
}
