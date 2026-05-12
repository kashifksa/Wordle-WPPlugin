<?php
require_once('../../../../wp-load.php');
if (class_exists('Wordle_API')) {
    $count = Wordle_API::refresh_json_cache();
    echo "JSON Cache Refreshed! Rows: $count";
} else {
    echo "Wordle_API class not found.";
}
