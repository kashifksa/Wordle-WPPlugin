<?php
require_once('../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

$files = [
    '../wordle-data.json',
    '../wordle-cache.json',
    '../../../uploads/wordle-hint-client/wordle-cache.json'
];

foreach ($files as $file) {
    echo "Checking file: $file\n";
    if (!file_exists($file)) {
        echo "File not found.\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $json = json_decode($content, true);
    if (!$json) {
        echo "Invalid JSON.\n";
        continue;
    }

    // Handle different formats
    $data = isset($json['data']) ? $json['data'] : $json;
    
    $count = 0;
    foreach ($data as $date => $item) {
        if (!is_array($item)) continue;
        
        $number = isset($item['number']) ? intval($item['number']) : 0;
        $word = $item['word'] ?? '';
        $hints = $item['hints'] ?? null;
        
        if ($number && $hints) {
            // Check DB
            $db_row = $wpdb->get_row($wpdb->prepare("SELECT hint1 FROM $table WHERE puzzle_number = %d", $number));
            if (!$db_row || empty($db_row->hint1)) {
                echo "MISSING HINT IN DB for #$number ($word). Saving...\n";
                $wpdb->update($table, array(
                    'hint1' => $hints['vague'] ?? $hints['hint1'] ?? '',
                    'hint2' => $hints['category'] ?? $hints['hint2'] ?? '',
                    'hint3' => $hints['specific'] ?? $hints['hint3'] ?? '',
                    'final_hint' => $hints['final'] ?? $hints['final_hint'] ?? '',
                ), array('puzzle_number' => $number));
                $count++;
            }
        }
    }
    echo "Saved $count hints from this file.\n\n";
}
echo "Done.\n";
