<?php
// Dynamically find wp-load.php
$path = dirname(__FILE__);
while ($path !== dirname($path)) {
    if (file_exists($path . '/wp-load.php')) {
        require_once($path . '/wp-load.php');
        break;
    }
    $path = dirname($path);
}
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$results = $wpdb->get_results("SELECT date, word, puzzle_number FROM $table ORDER BY date DESC LIMIT 10");

echo "LAST 10 PUZZLES:\n";
foreach ($results as $row) {
    echo $row->date . " | " . $row->word . " (#" . $row->puzzle_number . ")\n";
}
