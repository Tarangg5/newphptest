<?php
// Header set kar rahe hain taaki browser me text format me dikhe
header('Content-Type: text/plain; charset=utf-8');

// Playlist URL
$url = "https://allinonereborn.online/m3u/jtv145.m3u";

// cURL session start
$ch = curl_init();

// Sabhi zaruri headers jo OTT Navigator bhejta hai
$headers = [
    "User-Agent: OTT-Navigator/1.7.2.2 (Linux; Android 11; SDK 30)",
    "X-Requested-With: studio.scillarium.ottnavigator",
    "Accept: */*",
    "Connection: keep-alive"
];

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Redirect follow karega
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL error bypass karne ke liye
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

// Request execute karein
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch);
} else {
    // Agar redirect ho gaya toh yahan check hoga
    if (strpos($response, 't.me/allinonereborn_amit') !== false) {
        echo "Blocked: PHP Server also redirected to Telegram.\n";
        echo "HTTP Status Code: " . $httpCode . "\n\n";
        // Check karne ke liye ki response me kya hai
        echo $response;
    } else {
        // Asli M3U content dikhayega
        echo $response;
    }
}

curl_close($ch);
?>
