<?php
$url = $_GET['url'] ?? '';
$referrer = $_GET['referrer'] ?? '';

if (!$url) {
    http_response_code(400);
    exit("Thiếu ?url=");
}

// Hàm tải dữ liệu bằng cURL và stream trực tiếp ra trình duyệt
function stream_proxy($url, $referrer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADERFUNCTION => function($ch, $header) {
            // Ghi lại các header hợp lệ
            if (stripos($header, 'Content-Type:') === 0 ||
                stripos($header, 'Content-Length:') === 0 ||
                stripos($header, 'Accept-Ranges:') === 0 ||
                stripos($header, 'Content-Range:') === 0 ||
                stripos($header, 'Cache-Control:') === 0) {
                header($header);
            }
            return strlen($header);
        },
        CURLOPT_HTTPHEADER => [
            "Referer: $referrer",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
            "Connection: keep-alive",
        ],
    ]);

    $fp = fopen('php://output', 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

// Nếu là m3u8 thì rewrite lại các dòng .ts
if (pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) === 'm3u8') {
    [$content, $code, $ctype] = get_content($url, $referrer);
    if ($code >= 400 || !$content) {
        http_response_code($code);
        exit("Lỗi $code");
    }

    $parsed = parse_url($url);
    $base = $parsed['scheme'] . '://' . $parsed['host'] . rtrim(dirname($parsed['path']), '/');

    $lines = explode("\n", $content);
    $output = '';
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '#')) {
            $output .= $line . "\n";
        } elseif (!preg_match('#^https?://#', $trim)) {
            $full = $base . '/' . ltrim($trim, '/');
            $self = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
            $rewrite = $self . '?referrer=' . urlencode($referrer) . '&url=' . urlencode($full);
            $output .= $rewrite . "\n";
        } else {
            $output .= $line . "\n";
        }
    }

    header('Content-Type: application/vnd.apple.mpegurl');
    echo $output;
} else {
    // Stream .ts/.aac/.mp4 segment theo kiểu passthrough
    stream_proxy($url, $referrer);
}


// Hàm riêng tải file (dành cho .m3u8 text)
function get_content($url, $referrer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => [
            "Referer: $referrer",
            "User-Agent: Mozilla/5.0",
        ],
    ]);
    $content = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$content, $code, $ctype];
}
