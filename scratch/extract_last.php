<?php
$url = 'https://engaging-data.com/pages/scripts/wordlebot/wordlepuzzles.js';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

$start = strpos($response, '{');
$json_str = substr($response, $start);
// The file might not end with a semicolon if it's just the object
$json_str = rtrim($json_str, "; \n\r");

$all_stats = json_decode($json_str, true);

if ($all_stats) {
    $keys = array_keys($all_stats);
    $last_key = end($keys);
    echo "Last Key: " . $last_key . "\n";
    echo "Data: " . json_encode($all_stats[$last_key], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "JSON decode failed. Error: " . json_last_error_msg() . "\n";
    echo "End of string: " . substr($json_str, -50) . "\n";
}
