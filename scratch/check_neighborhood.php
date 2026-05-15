<?php
$json_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle-solver-data.json';
$data = json_decode(file_get_contents($json_file), true);
$words = $data['words'];
$idx = array_search('SAUCY', $words);
if ($idx !== false) {
    echo "Found SAUCY at index $idx\n";
    echo "Neighborhood: " . implode(', ', array_slice($words, max(0, $idx-2), 5)) . "\n";
} else {
    echo "SAUCY not found.\n";
    // Check where it SHOULD be
    foreach ($words as $i => $w) {
        if ($w > 'SAUCY') {
            echo "SAUCY is missing. It should be between " . $words[$i-1] . " and " . $w . "\n";
            break;
        }
    }
}
