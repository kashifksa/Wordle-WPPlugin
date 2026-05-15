<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$last_date = $wpdb->get_var("SELECT date FROM $table ORDER BY date DESC LIMIT 1");
echo "Master DB Count: $count\n";
echo "Master DB Last Date: $last_date\n";

require_once 'c:/xampp/htdocs/WordleHintsClient/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$count_c = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$last_date_c = $wpdb->get_var("SELECT date FROM $table ORDER BY date DESC LIMIT 1");
echo "Client DB Count: $count_c\n";
echo "Client DB Last Date: $last_date_c\n";
