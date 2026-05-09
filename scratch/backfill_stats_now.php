<?php
// Load WordPress
define('WP_USE_THEMES', false);
require_once('c:/xampp/htdocs/TodayWordle/wp-load.php');

require_once WORDLE_HINT_PATH . 'includes/class-wordle-db.php';
require_once WORDLE_HINT_PATH . 'includes/class-wordle-scraper.php';

global $wpdb;
$table = Wordle_DB::get_table_name();

// Get puzzles that have average_guesses but might be missing distribution
$puzzles = $wpdb->get_results("SELECT puzzle_number, date FROM $table ORDER BY date DESC LIMIT 30", ARRAY_A);

echo "Checking " . count($puzzles) . " puzzles...\n";

foreach ($puzzles as $p) {
    echo "Processing #{$p['puzzle_number']} ({$p['date']})... ";
    $stats = Wordle_Scraper::fetch_wordlebot_stats($p['puzzle_number']);
    
    if ($stats && !empty($stats['guess_distribution'])) {
        $wpdb->update($table, $stats, array('puzzle_number' => $p['puzzle_number']));
        echo "Updated!\n";
    } else {
        echo "Stats not found.\n";
    }
    
    // Sleep briefly to be nice to the source
    usleep(200000); 
}

// Clear the JSON cache to reflect changes
unlink(WORDLE_HINT_PATH . 'wordle-cache.json');
echo "Cache cleared.\n";
