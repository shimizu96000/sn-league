<?php
// データベース接続設定
// ラズパイの場合は /var/run/mysqld/mysqld.sock、XAMPP の場合は localhost を使用
$socket_path = '/var/run/mysqld/mysqld.sock';

// ローカル XAMPP の場合か、ラズパイの場合かで接続方法を判定
if (file_exists($socket_path)) {
    // ラズパイ本番環境（Unix socket が存在）
    $host = 'localhost:/var/run/mysqld/mysqld.sock';
} else {
    // XAMPP ローカル環境（Unix socket が存在しない）
    $host = 'localhost';
}

$dbname = 'mahjong_db';
$username = 'root';
$password = '';

try {
    // データベースに接続
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // エラー時に例外を出す
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // 配列としてデータを取得
            PDO::ATTR_EMULATE_PREPARES => false, // セキュリティ対策（静的プレースホルダ）
        ]
    );
} catch (PDOException $e) {
    // 接続失敗した場合
    header('Content-Type: text/plain; charset=UTF-8');
    exit('データベース接続失敗: ' . $e->getMessage());
}
?>