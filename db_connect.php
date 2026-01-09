<?php
// データベース接続設定
$dbname = 'mahjong_db';
$username = 'sn_league';
$password = 'sn_league_pass_123';

try {
    // Unix socket で接続（ラズパイ優先）
    $socket_path = '/var/run/mysqld/mysqld.sock';
    if (file_exists($socket_path)) {
        // ラズパイ：Unix socket で接続
        $pdo = new PDO(
            "mysql:unix_socket=$socket_path;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } else {
        // XAMPP ローカル環境：TCP で接続
        $pdo = new PDO(
            "mysql:host=localhost;dbname=$dbname;charset=utf8mb4",
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
} catch (PDOException $e) {
    // 接続失敗した場合
    header('Content-Type: text/plain; charset=UTF-8');
    exit('データベース接続失敗: ' . $e->getMessage());
}
?>