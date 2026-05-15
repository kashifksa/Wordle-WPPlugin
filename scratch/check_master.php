<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE word = 'SAUCY'");
echo "Count: " . $count . "\n";
