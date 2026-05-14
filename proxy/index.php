<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$url = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($url)) { echo json_encode(['error' => 'No URL']); exit; }

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?>
