<?php
require_once(__DIR__ . '/../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$empty_syns = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE synonyms = '[]' OR synonyms IS NULL OR synonyms = ''");
$empty_ants = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE antonyms = '[]' OR antonyms IS NULL OR antonyms = ''");
$empty_both = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE (synonyms = '[]' OR synonyms IS NULL OR synonyms = '') AND (antonyms = '[]' OR antonyms IS NULL OR antonyms = '')");

echo "Database Statistics for $table:\n";
echo "Total Records: $total\n";
echo "Empty Synonyms: $empty_syns (" . round(($empty_syns/$total)*100, 1) . "%)\n";
echo "Empty Antonyms: $empty_ants (" . round(($empty_ants/$total)*100, 1) . "%)\n";
echo "Empty Both: $empty_both (" . round(($empty_both/$total)*100, 1) . "%)\n";
