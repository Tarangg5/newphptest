<?php

// Headers for M3U output
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$cacheFile = "playlist.m3u"; // Is file mein data save hoga
$cacheTime = 7200; // 2 ghante tak fetch nahi karega, wahi file dikhayega

// 1. Check karein ki kya fresh cache file maujood hai
if (file_exists($cacheFile) && (time() - file_mtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// --- Agar cache purani hai, tabhi niche ka logic chalega ---

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
    if (!$channels) {
        die("#EXTM3U\n# Error: Could not parse JSON");
    }

    $linksToFetch = [];
    foreach ($channels as $index => $chInfo) {
        $linksToFetch[$index] = $chInfo['link'];
    }

    $pageContents = fetchAllChannels($linksToFetch);

    $m3uContent = "#EXTM3U\n\n";

    foreach ($channels as $index => $chInfo) {
        $html = $pageContents[$index];

        if (preg_match('/const SERVER_CONFIG\s*=\s*({.*?});/s', $html, $matches)) {
            $config = json_decode($matches[1], true);

            if ($config && isset($config['streamUrls'][0])) {
                $streamUrl = $config['streamUrls'][0];
                $cookie    = $config['primaryCookie'];
                $keyId     = $config['keyId'];
                $key       = $config['key'];

                $m3uContent .= "#EXTINF:-1 tvg-id=\"{$chInfo['id']}\" tvg-name=\"{$chInfo['name']}\" tvg-logo=\"{$chInfo['logo']}\" group-title=\"{$chInfo['group']}\",{$chInfo['name']}\n";
                $m3uContent .= "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
                $m3uContent .= "#KODIPROP:inputstream.adaptive.license_key={$keyId}:{$key}\n";
                $m3uContent .= "#EXTHTTP:{\"Cookie\":\"{$cookie}\"}\n";
                $m3uContent .= "{$streamUrl}\n\n";
            }
        }
    }

    // Naya data file mein save kar dein
    file_put_contents($cacheFile, $m3uContent);
    
    // Result dikhayein
    echo $m3uContent;

} catch (Exception $e) {
    echo "# Error: " . $e->getMessage();
}
?>
