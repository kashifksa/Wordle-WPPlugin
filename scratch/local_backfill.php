<?php
/**
 * Local Backfill Script for Wordle Stats
 * Uses engagingData.js to update the database.
 */

// Load WordPress
define( 'WP_USE_THEMES', false );
require_once( dirname(__FILE__) . '/../../../../wp-load.php' );

$file_path = ABSPATH . 'wp-content/plugins/Wordle-WPPlugin/engagingData.js';
if ( ! file_exists( $file_path ) ) {
    die( "File not found: $file_path" );
}

$content = file_get_contents( $file_path );

// Extract the JSON-like object from the JS variable
// wordlepuzzles={...}
if ( preg_match( '/wordlepuzzles\s*=\s*(\{.*\})/s', $content, $matches ) ) {
    $json_data = $matches[1];
    
    // Fix common JS-to-JSON issues (unquoted keys if any, but looking at the file they seem quoted)
    $data = json_decode( $json_data, true );

    if ( ! $data ) {
        die( "Failed to decode JSON. Error: " . json_last_error_msg() );
    }

    global $wpdb;
    $table = Wordle_DB::get_table_name();
    $updated = 0;
    $skipped = 0;

    echo "Starting backfill for " . count($data) . " puzzles...\n";

    foreach ( $data as $puzzle_num => $stats ) {
        $puzzle_num = (int)$puzzle_num;
        
        // Check if record exists and needs updating
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, difficulty FROM $table WHERE puzzle_number = %d", $puzzle_num ) );
        
        if ( ! $row ) {
            $skipped++;
            continue;
        }

        // Calculate Average and Difficulty
        $dist = $stats['individual']; // [1, 2, 15, 31, 30, 16]
        $total_won = array_sum( $dist );
        
        if ( $total_won > 0 ) {
            $sum_guesses = 0;
            foreach ( $dist as $index => $percent ) {
                $sum_guesses += ( $index + 1 ) * $percent;
            }
            $avg = round( $sum_guesses / $total_won, 2 );
            
            // Map Average to Difficulty (1-5)
            // 3.0-3.5: 1 (Very Easy)
            // 3.5-4.0: 2 (Easy)
            // 4.0-4.3: 3 (Moderate)
            // 4.3-4.6: 4 (Hard)
            // 4.6+: 5 (Insane)
            $diff = 3;
            if ( $avg < 3.5 ) $diff = 1;
            elseif ( $avg < 3.9 ) $diff = 2;
            elseif ( $avg < 4.2 ) $diff = 3;
            elseif ( $avg < 4.5 ) $diff = 4;
            else $diff = 5;

            $wpdb->update(
                $table,
                array(
                    'difficulty'         => $diff,
                    'average_guesses'    => $avg,
                    'guess_distribution' => json_encode( $dist ),
                    'url'                => 'https://engaging-data.com/wordle-guess-distribution/?p=' . $puzzle_num
                ),
                array( 'id' => $row->id )
            );
            $updated++;
        }
    }

    echo "Backfill Complete!\n";
    echo "Updated: $updated records\n";
    echo "Skipped: $skipped (Not in DB or no stats)\n";

    echo "Regenerating JSON Cache...\n";
    if ( class_exists('Wordle_API') ) {
        Wordle_API::refresh_json_cache();
        echo "Cache Updated!\n";
    }

} else {
    die( "Could not find wordlepuzzles object in JS file." );
}
