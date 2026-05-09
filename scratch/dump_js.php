<?php
require_once( 'c:/xampp/htdocs/TodayWordle/wp-load.php' );
$url = 'https://engaging-data.com/pages/scripts/wordlebot/wordlepuzzles.js';
$response = wp_remote_get( $url );
$body = wp_remote_retrieve_body( $response );
echo "LENGTH: " . strlen($body) . "\n";
echo "START: " . substr($body, 0, 500) . "\n";
echo "END: " . substr($body, -500) . "\n";
