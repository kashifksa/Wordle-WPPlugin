<?php
$json_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle-solver-data.json';
$data = json_decode(file_get_contents($json_file), true);
echo "New Master word count: " . count($data['words']) . "\n";
