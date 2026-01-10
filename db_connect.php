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
    // 既に作成済みのパスワードを使用（正確に合わせてください）
    $password = 'sn-league-pass-123';
}

// 接続先候補（順に試す）
$socket_path = '/var/run/mysqld/mysqld.sock';
$candidates = [];
if (!$is_xampp) {
    // 本番（ラズパイ）：優先は Unix socket（あれば）→127.0.0.1→実IP
    if (file_exists($socket_path)) {
        $candidates[] = ['dsn' => "mysql:unix_socket={$socket_path};dbname={$dbname};charset=utf8mb4", 'mode' => 'socket'];
    }
    $candidates[] = ['dsn' => "mysql:host=127.0.0.1;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => '127.0.0.1'];
    // 実IP（ローカルネットワーク）を追加しておく（必要なら変更）
    $candidates[] = ['dsn' => "mysql:host=192.168.0.158;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'lan_ip'];
} else {
    // XAMPP 環境
    $candidates[] = ['dsn' => "mysql:host=localhost;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'xampp_local'];
}

$log_file = sys_get_temp_dir() . '/sn_league_db_connect.log';
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
            ]
        );
        // 成功した接続方法をログに残す
        @file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "DB connected via {$c['mode']}\n", FILE_APPEND);
        break;
    } catch (PDOException $e) {
        $lastException = $e;
        @file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Failed {$c['mode']}: " . $e->getMessage() . "\n", FILE_APPEND);
        // 次候補へ
    }
}

if (!isset($pdo) || $pdo === null) {
    header('Content-Type: text/plain; charset=UTF-8');
    // 最終的なエラーを出力（ログにも出す）
    $msg = $lastException ? $lastException->getMessage() : 'unknown error';
    @file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Final failure: {$msg}\n", FILE_APPEND);
    exit('データベース接続失敗: ' . $msg);
}

?>
