<?php
// Errors ko hide karein taaki output sirf M3U text ho
error_reporting(0);

$urls = [
    'm3u' => "https://raw.githubusercontent.com/Tarangg5/sports/refs/heads/main/sonur.m3u",
    'hindiCookie' => "https://allrounderid2.pages.dev/api/star-1-hindi.json",
    'englishCookie' => "https://allrounder-live2.pages.dev/api/star-1.json",
    'ss2HindiHotstar' => "https://server.lrl45.workers.dev/channel/raw?=m3u",
    'ss2Hindi50fps' => "https://copy-karna-chor-da-bhai.pages.dev/TEMP/SS2H/"
];

$zeeChannels = ['Zee Cinemalu HD', 'Zee Cinema HD', '&Pictures HD', 'Zee Classic', 'Zee Cinemalu', 'Zee Power HD', '&TV HD', '&xplorHD', '&flix HD', 'Big Magic', 'Zee Action', 'Anmol Cinema', 'Anmol Cinema 2', 'Zee Cinema'];

// Parallel Fetching
$mh = curl_multi_init();
$handles = [];
foreach ($urls as $key => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_multi_add_handle($mh, $ch);
    $handles[$key] = $ch;
}
$running = null;
do { curl_multi_exec($mh, $running); } while ($running);

// Responses ko collect karein
$m3uContent = curl_multi_getcontent($handles['m3u']);
$ss2M3uRaw = curl_multi_getcontent($handles['ss2HindiHotstar']);
$ss2_50fpsHtml = curl_multi_getcontent($handles['ss2Hindi50fps']);

// JSON Safety Logic (Cloudflare ki tarah PHP mein safe parsing)
$hindiRaw = curl_multi_getcontent($handles['hindiCookie']);
$englishRaw = curl_multi_getcontent($handles['englishCookie']);

$hindiJson = json_decode($hindiRaw, true);
$englishJson = json_decode($englishRaw, true);

// Agar JSON invalid hai toh empty string set karein
$newHindi = (is_array($hindiJson) && isset($hindiJson['cookie'])) ? $hindiJson['cookie'] : '';
$newEnglish = (is_array($englishJson) && isset($englishJson['cookie'])) ? $englishJson['cookie'] : '';

foreach ($handles as $ch) { curl_multi_remove_handle($mh, $ch); curl_close($ch); }
curl_multi_close($mh);

// SS2 Cookies Extraction
preg_match('/hdntl=exp=[^"\'}]+/', $ss2M3uRaw, $m1);
$newSS2 = $m1[0] ?? '';

preg_match('/manualCookie:\s*"(__hdnea__=[^"]+)"/', $ss2_50fpsHtml, $m2);
$newSS2_50fps = $m2[1] ?? '';

// --- Safe Replacement Logic ---

// 1. Star Sports 1 Hindi HD 50fps
if (!empty($newHindi)) {
    preg_match('/Star Sports 1 Hindi HD 50fps[\s\S]*?#EXTHTTP:\{"Cookie":"([^"]+)"/', $m3uContent, $m);
    if (!empty($m[1])) $m3uContent = str_replace($m[1], $newHindi, $m3uContent);
}

// 2. Star Sports 1 HD English
if (!empty($newEnglish)) {
    preg_match('/Star Sports 1 HD[\s\S]*?#EXTHTTP:\{"Cookie":"([^"]+)"/', $m3uContent, $m);
    if (!empty($m[1])) $m3uContent = str_replace($m[1], $newEnglish, $m3uContent);
}

// 3. Star Sports 2 Hindi HD
if (!empty($newSS2)) {
    preg_match('/Star Sports 2 Hindi HD(?! 50fps)[\s\S]*?#EXTHTTP:\{"Cookie":"([^"]+)"/', $m3uContent, $m);
    if (!empty($m[1])) $m3uContent = str_replace($m[1], $newSS2, $m3uContent);
}

// 4. Star Sports 2 Hindi HD 50fps
if (!empty($newSS2_50fps)) {
    preg_match('/Star Sports 2 Hindi HD 50fps[\s\S]*?#EXTHTTP:\{"Cookie":"([^"]+)"/', $m3uContent, $m);
    if (!empty($m[1])) $m3uContent = str_replace($m[1], $newSS2_50fps, $m3uContent);
}

// Zee5 logic
$zeeSection = "\n#--- ZEE5 CHANNELS ---\n";
$lines = explode("\n", $ss2M3uRaw);
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '#EXTINF') === 0) {
        foreach ($zeeChannels as $ch) {
            if (strpos($lines[$i], $ch) !== false) {
                $zeeSection .= $lines[$i]."\n".($lines[$i+1]??"")."\n".($lines[$i+2]??"")."\n".($lines[$i+3]??"")."\n\n";
                break;
            }
        }
    }
}

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo $m3uContent . $zeeSection;
?>
