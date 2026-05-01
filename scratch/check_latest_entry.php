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

if (!defined('ABSPATH')) {
    die("Could not find wp-load.php");
}

require_once( ABSPATH . 'wp-content/plugins/WordleHintPro/includes/class-wordle-db.php' );

global $wpdb;
$table = Wordle_DB::get_table_name();
$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 1", ARRAY_A );

echo json_encode( $results, JSON_PRETTY_PRINT );
