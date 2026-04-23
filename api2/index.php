<?php
// File ka naam jahan data save hoga
$cacheFile = "playlist.m3u";
// Kitni der baad refresh karna hai (seconds mein) - 7200 seconds = 2 Ghante
$cacheTime = 7200; 

// Check karein ki kya file purani ho gayi hai ya exist nahi karti
if (file_exists($cacheFile) && (time() - file_mtime($cacheFile) < $cacheTime)) {
    // Agar file fresh hai, toh direct wahi dikha do
    header('Content-Type: text/plain; charset=utf-8');
    echo file_get_contents($cacheFile);
    exit;
}

// --- AGAR FILE PURANI HAI TOH YE LOGIC CHALEGA ---

set_time_limit(0);
ini_set('memory_limit', '512M');

$pastefyUrl = "https://pastefy.app/ZH3tseJk/raw";

function fetchAllChannels($urls) {
    $multiHandle = curl_multi_init();
    $handles = [];
    foreach ($urls as $id => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => '@cloudplay',
        ]);
        $handles[$id] = $ch;
        curl_multi_add_handle($multiHandle, $ch);
    }
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);
    $results = [];
    foreach ($handles as $id => $ch) {
        $results[$id] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiHandle);
    return $results;
}

try {
    $ch = curl_init($pastefyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $jsonRaw = curl_exec($ch);
    curl_close($ch);

    $channels = json_decode($jsonRaw, true);
    if (!$channels) die("Error parsing JSON");

    $linksToFetch = [];
    foreach ($channels as $index => $chInfo) {
        $linksToFetch[$index] = $chInfo['link'];
    }

    $pageContents = fetchAllChannels($linksToFetch);
    $m3uData = "#EXTM3U\n\n";

    foreach ($channels as $index => $chInfo) {
        $html = $pageContents[$index];
        if (preg_match('/const SERVER_CONFIG\s*=\s*({.*?});/s', $html, $matches)) {
            $config = json_decode($matches[1], true);
            if ($config && isset($config['streamUrls'][0])) {
                $streamUrl = $config['streamUrls'][0];
                $cookie    = $config['primaryCookie'];
                $keyId     = $config['keyId'];
                $key       = $config['key'];

                $m3uData .= "#EXTINF:-1 tvg-id=\"{$chInfo['id']}\" tvg-name=\"{$chInfo['name']}\" tvg-logo=\"{$chInfo['logo']}\" group-title=\"{$chInfo['group']}\",{$chInfo['name']}\n";
                $m3uData .= "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
                $m3uData .= "#KODIPROP:inputstream.adaptive.license_key={$keyId}:{$key}\n";
                $m3uData .= "#EXTHTTP:{\"Cookie\":\"{$cookie}\"}\n";
                $m3uData .= "{$streamUrl}\n\n";
            }
        }
    }

    // Result ko text file mein save kar dein
    file_put_contents($cacheFile, $m3uData);

    // Final Output dikhayein
    header('Content-Type: text/plain; charset=utf-8');
    echo $m3uData;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
