<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$row = $wpdb->get_row("SELECT first_known_use FROM $table WHERE word = 'EERIE'", ARRAY_A);
echo "EERIE Date: [" . $row['first_known_use'] . "]\n";
