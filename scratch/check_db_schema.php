<?php
require_once(__DIR__ . '/../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
echo "Columns in $table:\n";
foreach($columns as $col) {
    echo "- {$col->Field} ({$col->Type})\n";
}
