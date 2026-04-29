<?php
require_once( 'C:/xampp/htdocs/Wordlehint2/wp-load.php' );
require_once( 'C:/xampp/htdocs/Wordlehint2/includes/class-wordle-db.php' );

global $wpdb;
$table = Wordle_DB::get_table_name();
$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 1", ARRAY_A );

echo json_encode( $results, JSON_PRETTY_PRINT );
