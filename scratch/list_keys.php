<?php
$local_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/engagingData.js';
$body = file_get_contents($local_file);
$start = strpos($body, '{');
$json_str = substr($body, $start);
$json_str = rtrim($json_str, "; \n\r");
$all_stats = json_decode($json_str, true);

echo "First 10 keys:\n";
print_r(array_slice(array_keys($all_stats), 0, 10));

echo "\nLast 10 keys:\n";
print_r(array_slice(array_keys($all_stats), -10));
