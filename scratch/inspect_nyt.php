<?php
require_once( 'c:/xampp/htdocs/TodayWordle/wp-load.php' );

$date = date('Y-m-d');
$url = "https://www.nytimes.com/svc/wordle/v2/$date.json";

$response = wp_remote_get( $url );
if ( is_wp_error( $response ) ) {
    echo "Error: " . $response->get_error_message();
    exit;
}

$body = wp_remote_retrieve_body( $response );
echo "URL: $url\n";
echo "BODY:\n";
echo $body;
