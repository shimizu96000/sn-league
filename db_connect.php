<?php
// データベース接続設定
$dbname = 'mahjong_db';

// ラズパイの場合は sn_league、XAMPP の場合は root で接続
// ファイルパスでより確実に判定
$file_path = realpath(__FILE__);
if (strpos($file_path, 'xampp') !== false) {
    // XAMPP ローカル環境
    $username = 'root';
    $password = '';
    $host = 'localhost';
} else {
    // ラズパイ本番環境（Cloudflare トンネル経由も含む）
    $username = 'sn_league';
    $password = 'sn_league_pass_123';
    $host = 'localhost';  // ホスト名で接続
}

try {
    // TCP で接続
    $pdo = new PDO(
        "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // 接続失敗した場合
    header('Content-Type: text/plain; charset=UTF-8');
    exit('データベース接続失敗: ' . $e->getMessage());
}
?>