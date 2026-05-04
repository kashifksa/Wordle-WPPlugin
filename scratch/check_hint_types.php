<?php
require_once('../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$generating = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE hint1 = 'Generating...'");
$nulls = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE hint1 IS NULL");
$empty = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE hint1 = ''");

echo "Generating...: $generating\n";
echo "NULL: $nulls\n";
echo "Empty: $empty\n";
