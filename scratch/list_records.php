<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$rows = $wpdb->get_results("SELECT id, word, definition, synonyms, antonyms, example_sentence FROM $table LIMIT 20");
print_r($rows);
