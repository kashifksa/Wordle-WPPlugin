<?php
require_once( 'c:/xampp/htdocs/TodayWordle/wp-load.php' );
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$columns = $wpdb->get_results("DESCRIBE $table");
foreach ($columns as $col) {
    echo "Field: {$col->Field} | Type: {$col->Type}\n";
}
