<?php
/**
 * One-time cleanup script to remove Merriam-Webster formatting tokens from the database.
 * Version 3: Fixes legacy "centuryi", "centuryt" and trailing letters caused by previous bad regex.
 */
require_once 'C:/xampp/htdocs/TodayWordle/wp-load.php';

global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

echo "Starting Database Cleanup (v3)...\n";

function clean_text($text) {
    if (empty($text)) return $text;
    
    // 1. Keep text from semantic link tags ONLY {a_link|word}, {sx|word||}, etc.
    $text = preg_replace('/\{(?:a_link|d_link|i_link|sx|mat|qword)\|([^|}]*)[^}]*\}/', '$1', $text);
    
    // 2. Remove all other tokens entirely {ds|...}, {bc}, {it}, etc.
    $text = preg_replace('/\{[^}]*\}/', '', $text);
    
    // 3. Fix legacy artifacts from previous bad cleaning (centuryi, centuryt, 1522i)
    $text = preg_replace('/century[it]\b/', 'century', $text);
    $text = preg_replace('/(\d{4})[it]\b/', '$1', $text);
    
    return trim($text);
}

// Fetch all records
$rows = $wpdb->get_results("SELECT id, definition, etymology, first_known_use, example_sentence FROM $table");

$count = 0;
foreach ($rows as $row) {
    $updates = array();
    
    $clean_def = clean_text($row->definition);
    if ($clean_def !== $row->definition) $updates['definition'] = $clean_def;
    
    $clean_et = clean_text($row->etymology);
    if ($clean_et !== $row->etymology) $updates['etymology'] = $clean_et;
    
    $clean_date = clean_text($row->first_known_use);
    if ($clean_date !== $row->first_known_use) $updates['first_known_use'] = $clean_date;
    
    $clean_example = clean_text($row->example_sentence);
    if ($clean_example !== $row->example_sentence) $updates['example_sentence'] = $clean_example;
    
    if (!empty($updates)) {
        $wpdb->update($table, $updates, array('id' => $row->id));
        $count++;
    }
}

echo "Cleanup Complete! Cleaned/Fixed $count records.\n";
