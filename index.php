<?php
// Errors hide karne ke liye (Production ke liye)
error_reporting(0);

// Target URLs
$urls = [
    'm3u' => "https://raw.githubusercontent.com/Tarangg5/sports/refs/heads/main/sonur.m3u",
    'hindiCookie' => "https://allrounder-live2.pages.dev/api/cookie.json",
    'englishCookie' => "https://allrounder-live2.pages.dev/api/star-1.json",
    'ss2HindiHotstar' => "https://server.lrl45.workers.dev/channel/raw?=m3u",
    'ss2Hindi50fps' => "https://copy-karna-chor-da-bhai.pages.dev/TEMP/SS2H/"
];

// Zee5 channels list
$zeeChannels = [
    'Zee Cinemalu HD', 'Zee Cinema HD', '&Pictures HD', 'Zee Classic',
    'Zee Cinemalu', 'Zee Power HD', '&TV HD', '&xplorHD', '&flix HD',
    'Big Magic', 'Zee Action', 'Anmol Cinema', 'Anmol Cinema 2', 'Zee Cinema'
];

// Parallel Fetching using cURL Multi
$mh = curl_multi_init();
$handles = [];

foreach ($urls as $key => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_multi_add_handle($mh, $ch);
    $handles[$key] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
} while ($running);

// Responses collect karein
$m3uContent = curl_multi_getcontent($handles['m3u']);
$hindiJson = json_decode(curl_multi_getcontent($handles['hindiCookie']), true);
$englishJson = json_decode(curl_multi_getcontent($handles['englishCookie']), true);
$ss2M3uRaw = curl_multi_getcontent($handles['ss2HindiHotstar']);
$ss2_50fpsHtml = curl_multi_getcontent($handles['ss2Hindi50fps']);

foreach ($handles as $ch) {
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

// Cookie logic
$newHindiCookie = $hindiJson['cookie'] ?? '';
$newEnglishCookie = $englishJson['cookie'] ?? '';

preg_match('/hdntl=exp=[^"\'}]+/', $ss2M3uRaw, $ss2Match);
$newSS2Cookie = $ss2Match[0] ?? null;

preg_match('/manualCookie:\s*"(__hdnea__=[^"]+)"/', $ss2_50fpsHtml, $ss2_50fpsMatch);
$newSS2_50fpsCookie = $ss2_50fpsMatch[1] ?? null;

// --- Corrected Replacement Logic ---

// 1. Star Sports 1 Hindi HD 50fps
$m3uContent = preg_replace(
    '/(Star Sports 1 Hindi HD 50fps[\s\S]*?#EXTHTTP:\{"Cookie":")__hdnea__=[^"]+("\})/',
    '${1}' . $newHindiCookie . '${2}', 
    $m3uContent
);

// 2. Star Sports 1 HD (English)
$m3uContent = preg_replace(
    '/(Star Sports 1 HD[\s\S]*?#EXTHTTP:\{"Cookie":")__hdnea__=[^"]+("\})/',
    '${1}' . $newEnglishCookie . '${2}', 
    $m3uContent
);

// 3. Star Sports 2 Hindi HD (Hotstar)
if ($newSS2Cookie) {
    $m3uContent = preg_replace(
        '/(Star Sports 2 Hindi HD(?! 50fps)[\s\S]*?#EXTHTTP:\{"Cookie":")hdntl=[^"]+(")/',
        '${1}' . $newSS2Cookie . '${3}', 
        $m3uContent
    );
}

// 4. Star Sports 2 Hindi HD 50fps
if ($newSS2_50fpsCookie) {
    $m3uContent = preg_replace(
        '/(Star Sports 2 Hindi HD 50fps[\s\S]*?#EXTHTTP:\{"Cookie":")__hdnea__=[^"]+("\})/',
        '${1}' . $newSS2_50fpsCookie . '${2}', 
        $m3uContent
    );
}


// Zee5 Extraction
$zeeSection = "\n#--- ZEE5 CHANNELS ---\n";
$lines = explode("\n", $ss2M3uRaw);
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '#EXTINF') === 0) {
        $found = false;
        foreach ($zeeChannels as $ch) {
            if (strpos($lines[$i], $ch) !== false) {
                $found = true; break;
            }
        }
        if ($found) {
            $zeeSection .= $lines[$i] . "\n" . ($lines[$i+1] ?? "") . "\n" . ($lines[$i+2] ?? "") . "\n" . ($lines[$i+3] ?? "") . "\n\n";
        }
    }
}

// Final Output
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo $m3uContent . $zeeSection;
?>
