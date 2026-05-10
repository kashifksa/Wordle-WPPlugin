<?php
require_once( dirname( __FILE__ ) . '/../../../../wp-load.php' );
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$date = '2026-05-08';
$val = $wpdb->get_var( $wpdb->prepare( "SELECT guess_distribution FROM $table WHERE date = %s", $date ) );
echo "Val: '$val'\n";
echo "Length: " . strlen($val) . "\n";
echo "Decoded: " . var_export(json_decode($val, true), true) . "\n";
