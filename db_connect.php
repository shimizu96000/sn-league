<?php
// データベース接続設定（堅牢化）
$dbname = 'mahjong_db';

// ユーザー情報：ラズパイ本番は sn_league、XAMPP は root
$file_path = realpath(__FILE__);
$is_xampp = strpos($file_path, 'xampp') !== false;

if ($is_xampp) {
    $username = 'root';
    $password = '';
} else {
    $username = 'sn_league';
    // 実際に作成したパスワード。必要ならここを更新してください。
    $password = 'sn-league-pass-123';
}

// 接続先候補（順に試す）。コンテナ環境を考慮し host.docker.internal / Docker gateway も試す
$socket_path = '/var/run/mysqld/mysqld.sock';
$candidates = [];
if (!$is_xampp) {
    if (file_exists($socket_path)) {
        $candidates[] = ['dsn' => "mysql:unix_socket={$socket_path};dbname={$dbname};charset=utf8mb4", 'mode' => 'socket'];
    }
    // ループバック（コンテナ内の 127.0.0.1 はコンテナ自身なので通常失敗するが試す）
    $candidates[] = ['dsn' => "mysql:host=127.0.0.1;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => '127.0.0.1'];
    // ホスト名経由（localhost は socket を使う場合がある）
    $candidates[] = ['dsn' => "mysql:host=localhost;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'localhost'];
    // Docker for Mac/Windows の特別ホスト
    $candidates[] = ['dsn' => "mysql:host=host.docker.internal;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'host.docker.internal'];
    // Docker ブリッジのデフォルトゲートウェイ
    $candidates[] = ['dsn' => "mysql:host=172.17.0.1;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'docker_gateway'];
    // 実際の LAN IP（ラズパイのホストIP）
    $candidates[] = ['dsn' => "mysql:host=192.168.0.158;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'lan_ip'];
} else {
    $candidates[] = ['dsn' => "mysql:host=localhost;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'xampp_local'];
}

$lastException = null;

foreach ($candidates as $c) {
    try {
        $pdo = new PDO(
            $c['dsn'],
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
        // 成功した接続方法を Apache エラーログに残す
        error_log("[sn_league] DB connected via {$c['mode']} (DSN: {$c['dsn']})");
        break;
    } catch (PDOException $e) {
        $lastException = $e;
        error_log("[sn_league] Failed {$c['mode']}: " . $e->getMessage());
    }
}

if (!isset($pdo) || $pdo === null) {
    header('Content-Type: text/plain; charset=UTF-8');
    $msg = $lastException ? $lastException->getMessage() : 'unknown error';
    error_log("[sn_league] Final failure: {$msg}");
    exit('データベース接続失敗: ' . $msg);
}

?>
