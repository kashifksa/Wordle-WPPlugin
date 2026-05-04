<?php
require_once('../../../../wp-load.php');

global $wpdb;
$options = $wpdb->get_results("SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE '%wordle%'");

foreach ($options as $opt) {
    $val = get_option($opt->option_name);
    if (is_array($val)) {
        echo "Option: {$opt->option_name} (Array, Count: " . count($val) . ")\n";
        // If it's a large array, maybe it's the cache
        if (count($val) > 100) {
            echo "POSSIBLE CACHED HINTS FOUND IN OPTION {$opt->option_name}!\n";
        }
    } else {
        $len = strlen($val);
        echo "Option: {$opt->option_name} (String, Length: $len)\n";
        if ($len > 5000) {
            echo "POSSIBLE CACHED HINTS FOUND IN OPTION {$opt->option_name}!\n";
        }
    }
}
