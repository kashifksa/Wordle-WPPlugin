<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$row = $wpdb->get_row("SELECT * FROM $table WHERE word LIKE 'BUC%'", ARRAY_A);
print_r($row);
