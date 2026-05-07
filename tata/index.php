<?php
// Output buffering disable karna taaki data turant dikhe
if (ob_get_level()) ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no'); // Nginx ke liye

set_time_limit(0); // Script limit hatane ki koshish
ignore_user_abort(true);

$channelsUrl = "https://allinonereborn.online/tplay/channels.json";
$playBaseUrl = "https://allinonereborn.online/tplay/play.php?id=";

echo "#EXTM3U\n\n";
flush(); // Turant header bhej do

// 1. All Channels Fetch
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $channelsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Safari/537.36");
$jsonData = curl_exec($ch);
curl_close($ch);

$allChannels = json_decode($jsonData, true);
if (!$allChannels) exit;

// Ek batch mein kitne requests (Speed badhane ke liye)
$batch_size = 15; 
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
        curl_setopt($ch_h, CURLOPT_FOLLOWLOCATION, true);
        
        $curl_handles[$id] = $ch_h;
        curl_multi_add_handle($mh, $ch_h);
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        usleep(100); // CPU usage kam rakhne ke liye
    } while ($running > 0);

    foreach ($chunk as $channel) {
        $id = $channel['id'];
        $html = curl_multi_getcontent($curl_handles[$id]);
        
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
        curl_multi_remove_handle($mh, $curl_handles[$id]);
        curl_close($curl_handles[$id]);
    }
    curl_multi_close($mh);
    
    // Sabse important: Har batch ke baad buffer flush karo
    // Isse server ko lagega ki kaam chal raha hai aur timeout nahi dega
    flush(); 
}
?>
