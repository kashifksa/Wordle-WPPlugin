<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';
global $wpdb;

echo "Checking for future words (next 30 days)...\n";
$new_count = 0;
for ($i = 0; $i <= 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $existing = Wordle_DB::get_puzzle_by_date($date);
    if (!$existing) {
        echo "Scraping $date...\n";
        $result = Wordle_Scraper::fetch_and_process($date);
        if (!is_wp_error($result)) {
            $new_count++;
            echo "  Found: " . $result['word'] . "\n";
        }
    }
}

if ($new_count > 0) {
    echo "Added $new_count new future words.\n";
    Wordle_Solver::generate_solver_json();
    echo "Solver JSON updated.\n";
} else {
    echo "No new future words found.\n";
}
