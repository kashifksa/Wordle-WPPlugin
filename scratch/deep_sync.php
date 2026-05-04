<?php
require_once('../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

// Find ALL json files in htdocs/TodayWordle
$root = 'c:/xampp/htdocs/TodayWordle';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'json') {
        $files[] = $file->getPathname();
    }
}

foreach ($files as $file) {
    echo "Checking file: $file\n";
    $content = @file_get_contents($file);
    if (!$content) continue;
    $json = json_decode($content, true);
    if (!$json) continue;

    // Handle different formats
    $data = isset($json['data']) ? $json['data'] : $json;
    if (!is_array($data)) continue;
    
    $count = 0;
    foreach ($data as $key => $item) {
        if (!is_array($item)) continue;
        
        $number = isset($item['number']) ? intval($item['number']) : (isset($item['puzzle_number']) ? intval($item['puzzle_number']) : 0);
        $hints = $item['hints'] ?? null;
        
        if ($number && $hints) {
            $db_row = $wpdb->get_row($wpdb->prepare("SELECT hint1 FROM $table WHERE puzzle_number = %d", $number));
            if (!$db_row || empty($db_row->hint1)) {
                echo "FOUND HINT for #$number in $file. Saving...\n";
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
    if ($count > 0) {
        echo "SUCCESS: Saved $count hints from $file.\n";
    }
}
echo "Done.\n";
