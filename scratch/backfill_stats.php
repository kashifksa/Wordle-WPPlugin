<?php
require_once( 'c:/xampp/htdocs/TodayWordle/wp-load.php' );
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

echo "Fetching WordleBot archive...\n";
$url = 'https://engaging-data.com/pages/scripts/wordlebot/wordlepuzzles.js';
$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

if ( is_wp_error( $response ) ) {
    die( "Error: " . $response->get_error_message() );
}

$body = wp_remote_retrieve_body( $response );
if ( preg_match( '/wordlepuzzles\s*=\s*(\{.*\});/s', $body, $matches ) ) {
    $all_stats = json_decode( $matches[1], true );
    echo "Found stats for " . count($all_stats) . " puzzles.\n";

    $count = 0;
    foreach ($all_stats as $num => $s) {
        $num = intval($num);
        $avg = floatval($s['avg']);
        $dist = json_encode($s['individual']);

        // Calculate difficulty
        $difficulty = ( ( $avg - 3.4 ) / ( 4.8 - 3.4 ) ) * 4 + 1;
        $difficulty = max( 1.0, min( 5.0, round( $difficulty, 1 ) ) );

        $wpdb->update($table, array(
            'difficulty' => $difficulty,
            'average_guesses' => $avg,
            'guess_distribution' => $dist
        ), array('puzzle_number' => $num));
        $count++;
    }
    echo "Updated $count records.\n";
    
    // Trigger JSON cache refresh
    if (class_exists('Wordle_API')) {
        Wordle_API::refresh_json_cache();
        echo "JSON Cache refreshed.\n";
    }
} else {
    echo "Could not find stats in JS file.\n";
}
