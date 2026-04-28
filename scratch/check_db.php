<?php
require_once('wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$today = current_time('Y-m-d');
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE date = %s", $today));
if ($row) {
    echo "FOUND TODAY: " . $row->word . " (#" . $row->puzzle_number . ")\n";
} else {
    echo "NOT FOUND FOR TODAY ($today)\n";
}
