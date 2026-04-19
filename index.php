<?php
// Errors ko hide karein taaki output sirf M3U text ho
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// URLs
$idJsonUrl = "https://allrounderid2.pages.dev/id.json";
$m3uUrl = "https://raw.githubusercontent.com/Tarangg5/sports/refs/heads/main/sonur.m3u";
$hindiCookieUrl = "https://allrounder-live5.pages.dev/api/star-1-hindi.json";
$englishCookieUrl = "https://allrounderid2.pages.dev/api/star-1.json";
$ss2HindiHotstarUrl = "https://server.lrl45.workers.dev/channel/raw?=m3u";

$ipl4kChannels = ['M1-Hindi', 'M1-English', 'M1-Bhojpuri'];
$zeeChannels = [
    'Zee Cinemalu HD', 'Zee Cinema HD', '&Pictures HD', 'Zee Classic',
    'Zee Cinemalu', 'Zee Power HD', '&TV HD', '&xplorHD', '&flix HD',
    'Big Magic', 'Zee Action', 'Anmol Cinema', 'Anmol Cinema 2', 'Zee Cinema'
];

function fetchUrl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: "";
}

function fetchAllParallel($urls) {
    $multiHandle = curl_multi_init();
    $handles = [];

    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $handles[$key] = $ch;
        curl_multi_add_handle($multiHandle, $ch);
    }

    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    $results = [];
    foreach ($handles as $key => $ch) {
        $results[$key] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiHandle);

    return $results;
}

try {
    $responses = fetchAllParallel([
        'id'      => $idJsonUrl,
        'm3u'     => $m3uUrl,
        'hindi'   => $hindiCookieUrl,
        'english' => $englishCookieUrl,
        'ss2'     => $ss2HindiHotstarUrl,
    ]);

    $idData     = json_decode($responses['id'], true);
    $m3uContent = $responses['m3u'];
    $ss2M3uRaw  = $responses['ss2'];

    $sonuContent = "";
    $targetIds = ["hindi", "eng"];

    foreach ($targetIds as $targetId) {
        $source = null;
        foreach ($idData['iframes'] as $item) {
            if ($item['id'] === $targetId) {
                $source = $item;
                break;
            }
        }

        if ($source) {
            try {
                $pageHtml = fetchUrl($source['iframeSrc']);

                preg_match('/streamUrl\s*:\s*"([^"]+)"/', $pageHtml, $sMatch);
                preg_match('/keyId\s*:\s*"([^"]+)"/',    $pageHtml, $kiMatch);
                preg_match('/key\s*:\s*"([^"]+)"/',       $pageHtml, $kMatch);
                preg_match('/cookieUrl\s*:\s*"([^"]+)"/', $pageHtml, $cUrlMatch);

                if ($sMatch && $kiMatch && $kMatch && $cUrlMatch) {
                    $cData = json_decode(fetchUrl($cUrlMatch[1]), true);
                    $displayName = ($targetId === "hindi")
                        ? "Star Sports 1 Hindi HD"
                        : "Star Sports 1 English HD";

                    $sonuContent .= "#EXTINF:-1 tvg-id=\"{$targetId}\" tvg-name=\"{$displayName}\" tvg-logo=\"https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Sports_HD1.png\" group-title=\"⚡ LIVE TV\",{$displayName}\n";
                    $sonuContent .= "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
                    $sonuContent .= "#KODIPROP:inputstream.adaptive.license_key={$kiMatch[1]}:{$kMatch[1]}\n";
                    $sonuContent .= "#EXTHTTP:{\"Cookie\":\"{$cData['cookie']}\"}\n";
                    $sonuContent .= "{$sMatch[1]}\n\n";
                }
            } catch (Exception $e) {
                error_log("ID Fetch Error: " . $targetId);
            }
        }
    }

    $newHindiCookie   = null;
    $newEnglishCookie = null;

    $hindiJson = json_decode($responses['hindi'], true);
    if (isset($hindiJson['cookie'])) $newHindiCookie = $hindiJson['cookie'];

    $englishJson = json_decode($responses['english'], true);
    if (isset($englishJson['cookie'])) $newEnglishCookie = $englishJson['cookie'];

    preg_match('/hdntl=exp=[^\'"}]+/', $ss2M3uRaw, $ss2CookieMatch);
    $newSS2Cookie = $ss2CookieMatch ? $ss2CookieMatch[0] : null;

    if ($newHindiCookie) {
        $m3uContent = preg_replace(
            '/(Star Sports 1 Hindi HD 50fps[\s\S]*?#EXTHTTP:\{"Cookie":")__hdnea__=[^"]+("\})/',
            '${1}' . $newHindiCookie . '${2}',
            $m3uContent
        );
    }

    if ($newEnglishCookie) {
        $m3uContent = preg_replace(
            '/(Star Sports 1 HD[\s\S]*?#EXTHTTP:\{"Cookie":")__hdnea__=[^"]+("\})/',
            '${1}' . $newEnglishCookie . '${2}',
            $m3uContent
        );
    }

    if ($newSS2Cookie) {
        $m3uContent = preg_replace(
            '/(Star Sports 2 Hindi HD(?! 50fps)[\s\S]*?#EXTHTTP:\{"Cookie":")hdntl=[^"]+("(?:}|))/',
            '${1}' . $newSS2Cookie . '${2}',
            $m3uContent
        );
    }

    $iplSection       = "#--- TATA IPL 4K ---\n";
    $zeeSection       = "\n#--- ZEE5 CHANNELS ---\n";
    $sonyLivSection   = "\n#--- SONYLIV CHANNELS ---\n";
    $jioCinemaSection = "\n#--- JIO CINEMA CHANNELS ---\n";

    $lines           = explode("\n", $ss2M3uRaw);
    $currentCategory = null;
    $currentBlock    = "";

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "") continue;

        if (strpos($line, '#EXTINF') === 0) {
            if ($currentCategory && $currentBlock) {
                if ($currentCategory === "IPL")  $iplSection       .= $currentBlock . "\n";
                if ($currentCategory === "ZEE")  $zeeSection       .= $currentBlock . "\n";
                if ($currentCategory === "SONY") $sonyLivSection   .= $currentBlock . "\n";
                if ($currentCategory === "JIO")  $jioCinemaSection .= $currentBlock . "\n";
            }

            $currentBlock    = $line . "\n";
            $currentCategory = null;

            foreach ($ipl4kChannels as $ch) {
                if (strpos($line, $ch) !== false) { $currentCategory = "IPL"; break; }
            }
            if (!$currentCategory) {
                foreach ($zeeChannels as $ch) {
                    if (strpos($line, $ch) !== false) { $currentCategory = "ZEE"; break; }
                }
            }
            if (!$currentCategory && strpos($line, 'SonyLiv') !== false)    $currentCategory = "SONY";
            if (!$currentCategory && strpos($line, 'Jio Cinema') !== false) $currentCategory = "JIO";

        } elseif ($currentCategory) {
            $currentBlock .= $line . "\n";
        }
    }

    if ($currentCategory && $currentBlock) {
        if ($currentCategory === "IPL")  $iplSection       .= $currentBlock . "\n";
        if ($currentCategory === "ZEE")  $zeeSection       .= $currentBlock . "\n";
        if ($currentCategory === "SONY") $sonyLivSection   .= $currentBlock . "\n";
        if ($currentCategory === "JIO")  $jioCinemaSection .= $currentBlock . "\n";
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

    echo $sonuContent . $iplSection . $m3uContent . $zeeSection . $sonyLivSection . $jioCinemaSection;

} catch (Exception $error) {
    http_response_code(500);
    echo "Error: " . $error->getMessage();
}
?>
