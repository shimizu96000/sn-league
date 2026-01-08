<?php
// attendance_api.php
// cache_attendance.json を返すだけの軽量 API。フロントエンドからの非同期取得用。
$cache_file = __DIR__ . '/data/cache_attendance.json';
header('Content-Type: application/json; charset=utf-8');
if (file_exists($cache_file)) {
    echo file_get_contents($cache_file);
} else {
    echo json_encode([]);
}
