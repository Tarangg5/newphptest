<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0); // Script ko rukne na de

$channelsUrl = "https://allinonereborn.online/tplay/channels.json";
$playBaseUrl = "https://allinonereborn.online/tplay/play.php?id=";

// 1. Channels JSON fetch karein
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $channelsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Safari/537.36");
$jsonData = curl_exec($ch);
curl_close($ch);

$channels = json_decode($jsonData, true);
if (!$channels) {
    die("Error: JSON data nahi mila.");
}

echo "#EXTM3U\n\n";

// Multi-curl setup (Ek saath requests bhejne ke liye)
$batch_size = 20; // Ek baar mein 20 channels process honge
$chunks = array_chunk($channels, $batch_size);

foreach ($chunks as $chunk) {
    $mh = curl_multi_init();
    $curl_array = array();

    foreach ($chunk as $channel) {
        $id = $channel['id'];
        $url = $playBaseUrl . $id;
        $curl_array[$id] = curl_init($url);
        curl_setopt($curl_array[$id], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_array[$id], CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36");
        curl_setopt($curl_array[$id], CURLOPT_TIMEOUT, 10);
        curl_multi_add_handle($mh, $curl_array[$id]);
    }

    // Requests execute karein
    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running > 0);

    // Response process karein
    foreach ($chunk as $channel) {
        $id = $channel['id'];
        $html = curl_multi_getcontent($curl_array[$id]);
        
        if ($html) {
            preg_match('/mpd\s*:\s*["\'](.*?)["\']/', $html, $mpd);
            preg_match('/token\s*:\s*["\'](.*?)["\']/', $html, $token);
            preg_match('/drm\s*:\s*\{\s*["\'](.*?)["\']\s*:\s*["\'](.*?)["\']\s*\}/', $html, $drm);

            if (!empty($mpd[1]) && !empty($token[1]) && !empty($drm[1])) {
                echo "#EXTINF:-1 tvg-id=\"{$id}\" tvg-name=\"{$channel['name']}\" tvg-logo=\"{$channel['logo']}\" group-title=\"{$channel['category']}\",{$channel['name']}\n";
                echo "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
                echo "#KODIPROP:inputstream.adaptive.license_key={$drm[1]}:{$drm[2]}\n";
                echo "#EXTHTTP:{\"Cookie\":\"{$token[1]}\",\"Referer\":\"https://www.tataplaybinge.com/\",\"User-Agent\":\"Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36\"}\n";
                echo "{$mpd[1]}\n\n";
            }
        }
        curl_multi_remove_handle($mh, $curl_array[$id]);
        curl_close($curl_array[$id]);
    }
    curl_multi_close($mh);
    
    // Server load kam karne ke liye halka sa gap
    usleep(50000); 
}
?>
