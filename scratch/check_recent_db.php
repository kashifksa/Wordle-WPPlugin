<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$res = $wpdb->get_results("SELECT date, word FROM $table WHERE date >= '2026-05-01' ORDER BY date ASC");
foreach($res as $r) {
    echo $r->date . ": " . $r->word . "\n";
}
