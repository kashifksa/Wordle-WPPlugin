<?php
$url = 'https://engaging-data.com/pages/scripts/wordlebot/wordlepuzzles.js';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code . "\n";
if ($http_code == 200) {
    echo "First 100 chars: " . substr($response, 0, 100) . "\n";
} else {
    echo "Failed to fetch. Body: " . substr($response, 0, 500) . "\n";
}
