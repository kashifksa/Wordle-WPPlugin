<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$row = $wpdb->get_row("SELECT * FROM $table WHERE word LIKE '%bucolic%'", ARRAY_A);
if ($row) {
    echo "ID: " . $row['id'] . "\n";
    echo "Word: " . $row['word'] . "\n";
    echo "Definition: [" . $row['definition'] . "]\n";
    echo "Synonyms: [" . $row['synonyms'] . "]\n";
    echo "Antonyms: [" . $row['antonyms'] . "]\n";
    echo "Example: [" . $row['example_sentence'] . "]\n";
    echo "Example Type: " . gettype($row['example_sentence']) . "\n";
} else {
    echo "Not found\n";
}
