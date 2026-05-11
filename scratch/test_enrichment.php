<?php
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';
require_once 'includes/class-wordle-dictionary.php';

$words = ["UNFED", "MOULT", "WRUNG", "THOSE", "FOUND", "SLUNG", "CREPT", "WOVEN", "FUNGI", "WOKEN"];

foreach($words as $word) {
    echo "Fetching $word... ";
    $data = Wordle_Dictionary::fetch_enrichment($word);
    if (is_wp_error($data)) {
        echo "ERROR: " . $data->get_error_message() . PHP_EOL;
    } elseif (empty($data)) {
        echo "EMPTY RESULT" . PHP_EOL;
    } else {
        echo "SUCCESS (Definition: " . ($data['definition'] ?? 'N/A') . ")" . PHP_EOL;
    }
}
