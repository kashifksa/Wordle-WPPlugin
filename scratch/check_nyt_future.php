<?php
require_once 'c:/xampp/htdocs/TodayWordle/wp-load.php';

$dates = [
    date('Y-m-d', strtotime("+7 days")),
    date('Y-m-d', strtotime("+15 days")),
    date('Y-m-d', strtotime("+30 days")),
    date('Y-m-d', strtotime("+60 days")),
    date('Y-m-d', strtotime("+90 days")),
];

$base_url = 'https://www.nytimes.com/svc/wordle/v2/';

foreach ($dates as $date) {
    $url = $base_url . $date . '.json';
    echo "Checking $date: ";
    $response = wp_remote_get($url, [
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
        'timeout'    => 10,
    ]);
    
    if (is_wp_error($response)) {
        echo "Error: " . $response->get_error_message() . "\n";
    } else {
        $status = wp_remote_retrieve_response_code($response);
        echo "Status $status";
        if ($status === 200) {
            $body = wp_remote_retrieve_body($response);
            $json = json_decode($body, true);
            echo " - Word found: " . ($json['solution'] ?? 'NONE');
        }
        echo "\n";
    }
}
