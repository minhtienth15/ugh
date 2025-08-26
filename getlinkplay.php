<?php
error_reporting(0);
set_time_limit(0);

$url = isset($_GET['url']) ? $_GET['url'] : '';
$ref = isset($_GET['referrer']) ? $_GET['referrer'] : '';

if (!$url) {
    header("HTTP/1.1 400 Bad Request");
    die("Missing url parameter");
}

// Curl setup
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Giữ cookie để qua các bước validate (__test, token…)
$cookieFile = __DIR__ . "/cookie.txt";
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

// Header giả lập browser
$headers = [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
];
if ($ref) {
    $headers[] = "Referer: $ref";
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($response === false) {
    header("HTTP/1.1 502 Bad Gateway");
    die("Error fetching stream");
}

// Trả CORS header cho player web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

// Xác định nội dung
$contentType = $info['content_type'];
if (strpos($url, ".m3u8") !== false || strpos($contentType, "mpegurl") !== false) {
    header("Content-Type: application/vnd.apple.mpegurl");

    // Rewrite link .ts để đi qua proxy
    $base = dirname($url);
    $lines = explode("\n", $response);
    $newLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line && !str_starts_with($line, "#")) {
            if (!preg_match("~^https?://~", $line)) {
                $line = $base . "/" . $line;
            }
            $line = $_SERVER['PHP_SELF'] . "?url=" . urlencode($line) . "&referrer=" . urlencode($ref);
        }
        $newLines[] = $line;
    }
    echo implode("\n", $newLines);
} else {
    // TS segment
    header("Content-Type: video/MP2T");
    echo $response;
}
