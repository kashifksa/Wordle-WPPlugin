<?php
$json_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle-solver-data.json';
$data = json_decode(file_get_contents($json_file), true);
if (in_array('SAUCY', $data['words'])) {
    echo "YES, SAUCY is in the Master Solver JSON.\n";
} else {
    echo "NO, SAUCY is NOT in the Master Solver JSON.\n";
}
echo "Total words: " . count($data['words']) . "\n";
