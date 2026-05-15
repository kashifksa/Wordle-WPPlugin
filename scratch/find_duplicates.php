<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$res = $wpdb->get_results("SELECT word, COUNT(*) as c FROM $table GROUP BY word HAVING c > 1", ARRAY_A);

foreach($res as $r) {
    echo $r['word'] . " appears " . $r['c'] . " times.\n";
    $puzzles = $wpdb->get_col($wpdb->prepare("SELECT puzzle_number FROM $table WHERE word = %s", $r['word']));
    echo "  Puzzles: " . implode(', ', $puzzles) . "\n";
}
