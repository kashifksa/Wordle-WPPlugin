<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$row = $wpdb->get_row("SELECT * FROM $table WHERE word = 'RURAL'", ARRAY_A);
echo "Word: " . $row['word'] . "\n";
echo "Example Type: " . gettype($row['example_sentence']) . "\n";
echo "Example Value: [" . $row['example_sentence'] . "]\n";
