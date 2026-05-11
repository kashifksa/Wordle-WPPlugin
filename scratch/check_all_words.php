<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$rows = $wpdb->get_results("SELECT word FROM $table LIMIT 100");
foreach($rows as $r) {
    echo $r->word . ", ";
}
echo "\nTotal: " . count($rows);
