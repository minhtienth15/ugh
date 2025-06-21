<?php
$url = $_GET['url'] ?? '';
$referrer = $_GET['referrer'] ?? '';
if (!$url) {
    http_response_code(400);
    exit("Thiếu ?url=");
}

function fetch($url, $referrer){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            "Referer: $referrer",
            "User-Agent: Mozilla/5.0"
        ],
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$data, $code, $ctype];
}

[$data, $code, $ctype] = fetch($url, $referrer);
if ($code >= 400 || !$data) {
    http_response_code($code);
    exit("Lỗi $code");
}

$ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
if ($ext === 'm3u8') {
    $base = rtrim(dirname($url), '/');
    $lines = explode("\n", $data);
    $out = "";
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            $out .= $line . "\n";
        } elseif (preg_match('/\.m3u8$/i', $trimmed) || preg_match('/\.ts$/i', $trimmed)) {
            $full = preg_match('#^https?://#', $trimmed)
                  ? $trimmed
                  : $base . '/' . ltrim($trimmed, '/');

            $rewrite = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" .
                       $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] .
                       '?referrer=' . urlencode($referrer) .
                       '&url=' . urlencode($full);

            $out .= $rewrite . "\n";
        } else {
            $out .= $line . "\n";
        }
    }

    header("Content-Type: application/vnd.apple.mpegurl");
    echo $out;
} else {
    header("Content-Type: $ctype");
    echo $data;
}
