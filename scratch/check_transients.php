<?php
require_once('../../../../wp-load.php');

global $wpdb;
$transients = $wpdb->get_results("SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE '%_transient_wordle%'");

foreach ($transients as $t) {
    $name = str_replace('_transient_', '', $t->option_name);
    $val = get_transient($name);
    if (is_array($val)) {
        echo "Transient: $name (Array, Count: " . count($val) . ")\n";
    } else {
        $len = strlen($val);
        echo "Transient: $name (String, Length: $len)\n";
    }
}
