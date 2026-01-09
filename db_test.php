<?php
echo "=== データベース接続テスト ===\n\n";

$dbname = 'mahjong_db';
$username = 'sn_league';
$password = 'sn_league_pass_123';

echo "ホスト情報:\n";
echo "- ホスト名: " . gethostname() . "\n";
echo "- IP: " . $_SERVER['SERVER_ADDR'] ?? 'N/A' . "\n";
echo "- PHP ユーザー: " . get_current_user() . "\n\n";

echo "接続テスト (127.0.0.1):\n";
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ 接続成功\n";
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM results");
    $row = $result->fetch();
    echo "- 結果テーブルのレコード数: " . $row['cnt'] . "\n";
} catch (PDOException $e) {
    echo "✗ 接続失敗: " . $e->getMessage() . "\n";
}

echo "\n接続テスト (localhost):\n";
try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ 接続成功\n";
} catch (PDOException $e) {
    echo "✗ 接続失敗: " . $e->getMessage() . "\n";
}

echo "\nネットワーク情報:\n";
echo "- DNS: " . gethostbyname('127.0.0.1') . "\n";
echo "- DNS localhost: " . gethostbyname('localhost') . "\n";
?>
