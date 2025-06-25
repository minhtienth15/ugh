<?php
// Lấy giá trị url và referrer từ query string
$url = $_GET['url'] ?? '';
$referrer = $_GET['referrer'] ?? '';

if (!$url) {
    http_response_code(400);
    exit("Thiếu ?url=");
}

// Hàm tải nội dung từ URL bằng cURL
function fetch($url, $referrer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => [
            "Referer: $referrer",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)"
        ],
    ]);
    $data = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$data, $httpcode, $ctype];
}

// Gọi hàm tải
[$data, $code, $ctype] = fetch($url, $referrer);

if ($code >= 400 || !$data) {
    http_response_code($code);
    exit("Lỗi $code");
}

// Xử lý nếu là m3u8
$ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
if ($ext === 'm3u8') {
    $parsed = parse_url($url);
    $scheme = $parsed['scheme'] ?? 'https';
    $host = $parsed['host'] ?? '';
    $dir = rtrim(dirname($parsed['path']), '/');
    $base_url = "$scheme://$host$dir";

    $lines = explode("\n", $data);
    $output = '';

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '' || str_starts_with($trim, '#')) {
            $output .= $line . "\n";
        } elseif (!preg_match('#^https?://#', $trim)) {
            // Rewrite đường dẫn tương đối
            $full_url = $base_url . '/' . ltrim($trim, '/');
            $self = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
            $rewrite = $self . '?referrer=' . urlencode($referrer) . '&url=' . urlencode($full_url);
            $output .= $rewrite . "\n";
        } else {
            // Giữ nguyên link tuyệt đối
            $output .= $line . "\n";
        }
    }

    header('Content-Type: application/vnd.apple.mpegurl');
    echo $output;
} else {
    // Không phải m3u8 → trả dữ liệu như bình thường
    header("Content-Type: $ctype");
    echo $data;
}
