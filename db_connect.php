<?php
// データベース接続設定
$dbname = 'mahjong_db';

// 環境判定：ラズパイか XAMPP か
$is_raspi = file_exists('/etc/os-release');
$is_xampp = strpos(__FILE__, 'xampp') !== false || getenv('XAMPP_HOME');

if ($is_raspi && !$is_xampp) {
    // ラズパイ本番環境
    $username = 'sn_league';
    $password = 'sn_league_pass_123';
} else {
    // XAMPP ローカル環境
    $username = 'root';
    $password = '';
}

try {
    // TCP で接続
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4",
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