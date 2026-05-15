<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE word = %s", 'SAUCY'));
echo "Count in Master DB: " . $count . "\n";

require_once 'c:/xampp/htdocs/WordleHintsClient/wp-load.php';
// Reset $wpdb because we required another wp-load (it might have overwritten)
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$count_client = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE word = %s", 'SAUCY'));
echo "Count in Client DB: " . $count_client . "\n";
