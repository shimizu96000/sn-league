<?php
// データベース接続設定
$dbname = 'mahjong_db';

// ファイルパスで環境判定
$file_path = realpath(__FILE__);
if (strpos($file_path, 'xampp') !== false) {
    // Windows XAMPP用
    $username = 'root';
    $password = '';
    $host = 'localhost';
} else {
    // ラズパイ本番用（ここをさっき作ったユーザーに合わせる）
    $username = 'sn_league';
    $password = 'sn-league-pass-123';
    $host = '127.0.0.1'; 
}

// 接続文字列（TCP接続を強制）
$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=UTF-8');
    // エラーの詳細を表示
    exit('データベース接続失敗: ' . $e->getMessage());
}
?>
