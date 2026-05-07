<?php
// Script execution time badhane ki koshish




$channelsUrl = "https://allinonereborn.online/tplay/channels.json";
$playBaseUrl = "https://allinonereborn.online/tplay/play.php?id=";

// 1. JSON Fetch karo
$ch = curl_init($channelsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Safari/537.36");
$jsonData = curl_exec($ch);
curl_close($ch);

$allChannels = json_decode($jsonData, true);
if (!$allChannels) {
    die("#EXTM3U\n# Error: JSON load nahi hua.");
}

// Result store karne ke liye variable
$output = "#EXTM3U\n\n";

// Batch size (Speed ke liye ek saath 25 requests)
$batch_size = 25; 
$chunks = array_chunk($allChannels, $batch_size);

foreach ($chunks as $chunk) {
    $mh = curl_multi_init();
    $curl_handles = [];

    foreach ($chunk as $channel) {
        $id = $channel['id'];
        $ch_h = curl_init($playBaseUrl . $id);
        curl_setopt($ch_h, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_h, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36");
        curl_setopt($ch_h, CURLOPT_TIMEOUT, 15);
        
        $curl_handles[$id] = $ch_h;
        curl_multi_add_handle($mh, $ch_h);
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running > 0);

    foreach ($chunk as $channel) {
        $id = $channel['id'];
        $html = curl_multi_getcontent($curl_handles[$id]);
        
        if ($html) {
            preg_match('/mpd\s*:\s*["\'](.*?)["\']/', $html, $mpd);
            preg_match('/token\s*:\s*["\'](.*?)["\']/', $html, $token);
            preg_match('/drm\s*:\s*\{\s*["\'](.*?)["\']\s*:\s*["\'](.*?)["\']\s*\}/', $html, $drm);

            if (!empty($mpd[1]) && !empty($token[1]) && !empty($drm[1])) {
                $output .= "#EXTINF:-1 tvg-id=\"{$id}\" tvg-name=\"{$channel['name']}\" tvg-logo=\"{$channel['logo']}\" group-title=\"{$channel['category']}\",{$channel['name']}\n";
                $output .= "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
                $output .= "#KODIPROP:inputstream.adaptive.license_key={$drm[1]}:{$drm[2]}\n";
                $output .= "#EXTHTTP:{\"Cookie\":\"{$token[1]}\",\"Referer\":\"https://www.tataplaybinge.com/\",\"User-Agent\":\"Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36\"}\n";
                $output .= "{$mpd[1]}\n\n";
            }
        }
        curl_multi_remove_handle($mh, $curl_handles[$id]);
        curl_close($curl_handles[$id]);
    }
    curl_multi_close($mh);
}

// Jab sab khatam ho jaye, tab ek saath print karo
echo $output;
?>
