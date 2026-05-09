<?php
// Diagnostics script to check why backfill is failing
define('WP_USE_THEMES', false);
require_once('c:/xampp/htdocs/TodayWordle/wp-load.php');

require_once WORDLE_HINT_PATH . 'includes/class-wordle-db.php';
require_once WORDLE_HINT_PATH . 'includes/class-wordle-scraper.php';

global $wpdb;
$table = Wordle_DB::get_table_name();

// Get a few of the missing puzzles
$missing = $wpdb->get_results("SELECT puzzle_number, date FROM $table WHERE difficulty IS NULL OR difficulty = 0 LIMIT 5", ARRAY_A);

echo "Diagnostic for " . count($missing) . " missing puzzles:\n";

foreach ($missing as $p) {
    echo "Checking #{$p['puzzle_number']} ({$p['date']})...\n";
    $stats = Wordle_Scraper::fetch_wordlebot_stats($p['puzzle_number']);
    
    if (is_wp_error($stats)) {
        echo "  ❌ Error: " . $stats->get_error_message() . "\n";
    } elseif ($stats === false) {
        echo "  ❌ Result: False (Puzzle likely not found in the source file)\n";
    } else {
        echo "  ✔ Success! Data found.\n";
    }
}
