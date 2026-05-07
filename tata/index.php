<?php
// Script ko timeout hone se bachane ke liye limit hatana
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0); 

$channelsUrl = "https://allinonereborn.online/tplay/channels.json";
$playBaseUrl = "https://allinonereborn.online/tplay/play.php?id=";

// Browser jaisa User-Agent
$options = [
    "http" => [
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
    ]
];
$context = stream_context_create($options);

try {
    // 1. Saare channels ki list load karein
    $jsonData = file_get_contents($channelsUrl, false, $context);
    if ($jsonData === false) {
        die("Error: Channels JSON load nahi ho raha.");
    }

    $channels = json_decode($jsonData, true);

    echo "#EXTM3U\n\n";

    foreach ($channels as $channel) {
        $id = $channel['id'];
        $name = $channel['name'];
        $logo = $channel['logo'];
        $category = $channel['category'];

        // 2. Har ID ka play page fetch karein
        $playHtml = file_get_contents($playBaseUrl . $id, false, $context);

        if ($playHtml) {
            // MPD Link Extract karein
            preg_match('/mpd\s*:\s*["\'](.*?)["\']/', $playHtml, $mpdMatch);
            // Token Extract karein
            preg_match('/token\s*:\s*["\'](.*?)["\']/', $playHtml, $tokenMatch);
            // DRM Keys Extract karein
            preg_match('/drm\s*:\s*\{\s*["\'](.*?)["\']\s*:\s*["\'](.*?)["\']\s*\}/', $playHtml, $drmMatch);

            if (!empty($mpdMatch[1]) && !empty($tokenMatch[1]) && !empty($drmMatch[1])) {
                $mpd = $mpdMatch[1];
                $token = $tokenMatch[1];
                $keyId = $drmMatch[1];
                $keyVal = $drmMatch[2];

                // M3U Entry Format
                echo "#EXTINF:-1 tvg-id=\"$id\" tvg-name=\"$name\" tvg-logo=\"$logo\" group-title=\"$category\",$name\n";
                echo "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
                echo "#KODIPROP:inputstream.adaptive.license_key=$keyId:$keyVal\n";
                echo "#EXTHTTP:{\"Cookie\":\"$token\",\"Referer\":\"https://www.tataplaybinge.com/\",\"User-Agent\":\"Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36\"}\n";
                echo "$mpd\n\n";
            }
        }
        // Thoda break taaki server block na kare (Optional)
        // usleep(100000); 
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
