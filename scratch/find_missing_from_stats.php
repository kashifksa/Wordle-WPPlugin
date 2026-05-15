<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';

// 1. Get words from CSV
$csv_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle_dataset_enriched.csv';
$csv_words = [];
if ( file_exists( $csv_file ) ) {
    if ( ( $handle = fopen( $csv_file, "r" ) ) !== FALSE ) {
        fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (isset($data[2])) {
                $word = strtoupper(trim($data[2]));
                if (strlen($word) === 5) $csv_words[$word] = true;
            }
        }
        fclose($handle);
    }
}

// 2. Get words from DB
global $wpdb;
$db_words = $wpdb->get_col("SELECT word FROM {$wpdb->prefix}wordle_data");
foreach ($db_words as $w) {
    $word = strtoupper(trim($w));
    if (strlen($word) === 5) $csv_words[$word] = true;
}

// 3. Get words from EngagingData JS
$js_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/engagingData.js';
$missing = [];
if (file_exists($js_file)) {
    $content = file_get_contents($js_file);
    // Find "solution":"XXXXX"
    if (preg_match_all('/"solution":"([A-Z]{5})"/', $content, $matches)) {
        foreach ($matches[1] as $word) {
            if (!isset($csv_words[$word])) {
                $missing[] = $word;
            }
        }
    }
}

$missing = array_unique($missing);
sort($missing);

echo "Total words in current system: " . count($csv_words) . "\n";
echo "Total missing words found in stats file: " . count($missing) . "\n";

if (!empty($missing)) {
    echo "Adding missing words to DB/Solver...\n";
    foreach ($missing as $word) {
        echo "Found: $word\n";
        // We don't have full info for these, but we can at least add them to the solver list
        // by inserting them into the DB with minimal info, which will then get picked up by the JSON generator.
    }
} else {
    echo "No missing words found in stats file.\n";
}
