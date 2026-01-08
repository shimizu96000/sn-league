<?php
// データベース接続設定
$host = 'localhost';
$dbname = 'mahjong_db';
$username = 'root'; // XAMPPの初期ユーザー
$password = '';     // XAMPPの初期パスワードは空

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