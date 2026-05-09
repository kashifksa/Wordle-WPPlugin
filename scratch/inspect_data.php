<?php
$url = 'https://engaging-data.com/pages/scripts/wordlebot/wordlepuzzles.js';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

if (preg_match('/wordlepuzzles\s*=\s*(\{.*\});/s', $response, $matches)) {
    $json_str = $matches[1];
    $all_stats = json_decode($json_str, true);
    
    // Find the latest one
    $keys = array_keys($all_stats);
    $last_key = end($keys);
    $sample = $all_stats[$last_key];
    
    echo "Sample Key: " . $last_key . "\n";
    echo "Sample Data: " . json_encode($sample, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Regex failed to match.\n";
    echo "Response start: " . substr($response, 0, 500) . "\n";
}
