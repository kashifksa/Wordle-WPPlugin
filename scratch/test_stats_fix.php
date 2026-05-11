<?php
require_once(__DIR__ . '/../../../../wp-load.php');
require_once(WORDLE_HINT_PATH . 'includes/class-wordle-scraper.php');

global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$puzzles = $wpdb->get_results("SELECT puzzle_number FROM $table WHERE puzzle_number BETWEEN 552 AND 556");

if (empty($puzzles)) {
    die("No puzzles found in range 552-556.\n");
}

echo "Testing stats fetch for " . count($puzzles) . " puzzles...\n";

foreach ($puzzles as $p) {
    echo "Processing #{$p->puzzle_number}... ";
    $stats = Wordle_Scraper::fetch_wordlebot_stats($p->puzzle_number);
    if ($stats && !is_wp_error($stats)) {
        echo "Found! (Difficulty: {$stats['difficulty']}, Avg: {$stats['average_guesses']})\n";
    } else {
        echo "Not found. " . (is_wp_error($stats) ? $stats->get_error_message() : '') . "\n";
    }
}
