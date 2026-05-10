<?php
require_once( dirname( __FILE__ ) . '/../../../../wp-load.php' );
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$date = '2026-05-08';
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE date = %s", $date ), ARRAY_A );
echo "Date: " . $row['date'] . "\n";
echo "Word: " . $row['word'] . "\n";
echo "Avg Guesses: " . $row['average_guesses'] . "\n";
echo "Distribution: " . $row['guess_distribution'] . "\n";
echo "Type: " . gettype($row['guess_distribution']) . "\n";
