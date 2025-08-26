<?php
error_reporting(0);
set_time_limit(0);

$url = isset($_GET['url']) ? $_GET['url'] : '';
$ref = isset($_GET['referrer']) ? $_GET['referrer'] : '';

if (!$url) {
    header("HTTP/1.1 400 Bad Request");
    exit("Missing url");
}

// Khởi tạo cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$headers = ["User-Agent: Mozilla/5.0"];
if ($ref) $headers[] = "Referer: $ref";
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($response === false) {
    header("HTTP/1.1 502 Bad Gateway");
    exit("Fetch error");
}

// Cho phép phát HLS trên web
header("Access-Control-Allow-Origin: *");

// Xác định loại nội dung
if (strpos($url, ".m3u8") !== false) {
    header("Content-Type: application/vnd.apple.mpegurl");
} elseif (strpos($url, ".ts") !== false) {
    header("Content-Type: video/MP2T");
} else {
    header("Content-Type: " . ($info['content_type'] ?? "application/octet-stream"));
}

echo $response;
