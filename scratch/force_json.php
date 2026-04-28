<?php
// Find wp-load.php
$path = dirname(__FILE__);
while ($path !== dirname($path)) {
    if (file_exists($path . '/wp-load.php')) {
        require_once($path . '/wp-load.php');
        break;
    }
    $path = dirname($path);
}

if (!defined('ABSPATH')) {
    die("Could not find wp-load.php");
}

echo "Generating JSON cache...\n";
require_once(ABSPATH . 'wp-content/plugins/Wordlehint2/includes/class-wordle-api.php');
require_once(ABSPATH . 'wp-content/plugins/Wordlehint2/includes/class-wordle-db.php');

$result = Wordle_API::refresh_json_cache();
if ($result) {
    echo "SUCCESS: JSON cache generated.\n";
} else {
    echo "FAILED: JSON cache generation failed. Check if DB has data for yesterday, today, and tomorrow.\n";
    
    // Debug DB
    global $wpdb;
    $table = $wpdb->prefix . 'wordle_data';
    $dates = [
        date('Y-m-d', strtotime('-1 day')),
        date('Y-m-d'),
        date('Y-m-d', strtotime('+1 day'))
    ];
    foreach ($dates as $d) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT word FROM $table WHERE date = %s", $d));
        echo "$d: " . ($row ? "FOUND ($row->word)" : "MISSING") . "\n";
    }
}
