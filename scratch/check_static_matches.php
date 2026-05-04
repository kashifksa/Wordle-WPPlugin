<?php
require_once('../../../../wp-load.php');
require_once('../includes/class-wordle-static-hints.php');

global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$missing = $wpdb->get_results("SELECT word, puzzle_number FROM $table WHERE hint1 IS NULL OR hint1 = '' OR hint1 = 'Generating...'");

echo "Checking " . count($missing) . " missing words against static dictionary...\n";

$found = 0;
foreach ($missing as $row) {
    if (Wordle_Static_Hints::has_hints($row->word)) {
        echo "FOUND static hints for #$row->puzzle_number ($row->word). Saving...\n";
        $hints = Wordle_Static_Hints::get_hints($row->word);
        $wpdb->update($table, $hints, array('puzzle_number' => $row->puzzle_number));
        $found++;
    }
}

echo "Done. Saved $found hints from static dictionary.\n";
