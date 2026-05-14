<?php
header('Content-Type: application/vnd.apple.mpegurl');
header('Access-Control-Allow-Origin: *');

$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'live';

if (strlen($searchQuery) < 3) {
    echo "#EXTM3U\n#EXTINF:-1,Search min 3 chars\nhttp://google.com";
    exit;
}

$host = "http://datahub11.com";
$username = "Anthony2";
$password = "Anthony123";

$action = 'get_live_streams';
if ($type === 'vod') $action = 'get_vod_streams';
if ($type === 'series') $action = 'get_series';

$apiUrl = "$host/player_api.php?username=$username&password=$password&action=$action";

// Fetching data using PHP file_get_contents or cURL
$options = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
    ]
];
$context = stream_context_create($options);
$response = @file_get_contents($apiUrl, false, $context);

if ($response === FALSE) {
    echo "#EXTM3U\n#EXTINF:-1,Server Connection Failed\nhttp://error.com";
    exit;
}

$items = json_decode($response, true);

if (!is_array($items)) {
    echo "#EXTM3U\n#EXTINF:-1,Invalid Data From Provider\nhttp://error.com";
    exit;
}

echo "#EXTM3U\n";
$query = strtolower($searchQuery);

foreach ($items as $item) {
    if (isset($item['name']) && strpos(strtolower($item['name']), $query) !== false) {
        $finalUrl = "";
        if ($type === 'live') {
            $finalUrl = "$host/live/$username/$password/{$item['stream_id']}.ts";
        } else if ($type === 'vod') {
            $ext = isset($item['container_extension']) ? $item['container_extension'] : 'mp4';
            $finalUrl = "$host/movie/$username/$password/{$item['stream_id']}.$ext";
        } else if ($type === 'series') {
            $finalUrl = "SERIES_ID:{$item['series_id']}";
        }
        
        $logo = isset($item['cover']) ? $item['cover'] : (isset($item['stream_icon']) ? $item['stream_icon'] : '');
        echo "#EXTINF:-1 tvg-logo=\"$logo\",{$item['name']}\n$finalUrl\n";
    }
}
?>
