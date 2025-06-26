<?php
// Trang chủ trắng để tránh lỗi 404 trên Render
http_response_code(200);
header("Content-Type: text/plain");
echo "Trang chủ trống - dịch vụ hoạt động.";
