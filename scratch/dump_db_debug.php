<?php
require_once( dirname(__FILE__) . '/../../../../wp-load.php' );
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

echo "Database Name: " . DB_NAME . "\n";
echo "Table Prefix: " . $wpdb->prefix . "\n";
echo "Host: " . DB_HOST . "\n\n";

$tables = $wpdb->get_col("SHOW TABLES");
echo "All Tables in DB:\n";
foreach($tables as $t) {
    echo "- $t\n";
}

$table = $wpdb->prefix . 'wordle_data';
echo "\nChecking Table: $table\n";

$cols = $wpdb->get_results("DESCRIBE $table");
echo "Columns:\n";
foreach($cols as $c) {
    echo "- " . $c->Field . " (" . $c->Type . ")\n";
}

$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "\nTotal Rows in $table: $count\n";

$row = $wpdb->get_row("SELECT * FROM $table WHERE id = 1789", ARRAY_A);
echo "\nRow 1789 Check:\n";
if ($row) {
    print_r($row);
} else {
    echo "ID 1789 NOT FOUND IN THIS TABLE!\n";
}
