<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';

$csv_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle_dataset_enriched.csv';
$csv_words = [];
if ( file_exists( $csv_file ) ) {
    if ( ( $handle = fopen( $csv_file, "r" ) ) !== FALSE ) {
        fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (isset($data[2])) {
                $word = strtoupper(trim($data[2]));
                if (strlen($word) === 5) {
                    $csv_words[$word] = [
                        'date' => $data[0],
                        'number' => $data[1]
                    ];
                }
            }
        }
        fclose($handle);
    }
}

global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$db_words = $wpdb->get_col("SELECT word FROM $table");
$db_words = array_map('strtoupper', $db_words);

$missing_from_db = [];
foreach ($csv_words as $word => $info) {
    if (!in_array($word, $db_words)) {
        $missing_from_db[$word] = $info;
    }
}

echo "Total words in CSV: " . count($csv_words) . "\n";
echo "Missing from DB: " . count($missing_from_db) . "\n";

foreach ($missing_from_db as $word => $info) {
    echo "Missing: $word (Date: {$info['date']}, #{$info['number']})\n";
}
