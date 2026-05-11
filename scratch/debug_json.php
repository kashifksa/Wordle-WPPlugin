<?php
$local_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/engagingData.js';
$body = file_get_contents($local_file);
$start = strpos($body, '{');
if ($start === false) die("No { found\n");
$json_str = substr($body, $start);
$json_str = rtrim($json_str, "; \n\r");
$all_stats = json_decode($json_str, true);

if ($all_stats === null) {
    echo "JSON decode failed: " . json_last_error_msg() . "\n";
    // Let's see the end of the string
    echo "End of string: " . substr($json_str, -20) . "\n";
} else {
    echo "JSON decode success! Found " . count($all_stats) . " puzzles.\n";
    if (isset($all_stats["552"])) {
        echo "Puzzle 552 found!\n";
    } else {
        echo "Puzzle 552 NOT found.\n";
    }
}
