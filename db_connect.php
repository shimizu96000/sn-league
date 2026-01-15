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
// コンテナ内かどうかを判定（.dockerenv が存在する一般的な方法）
$in_container = file_exists('/.dockerenv') || getenv('IN_DOCKER') === '1';

// 任意の外部DB接続（例: LAN上の別ホスト）を環境変数で指定できるようにする
// 例) DB_HOST=192.168.0.157 DB_PORT=3306
$env_db_host = trim((string) getenv('DB_HOST'));
$env_db_port = trim((string) getenv('DB_PORT'));
$env_db_port = ($env_db_port !== '' && ctype_digit($env_db_port)) ? (int) $env_db_port : 3306;

if (!$is_xampp) {
    if (file_exists($socket_path)) {
        $candidates[] = ['dsn' => "mysql:unix_socket={$socket_path};dbname={$dbname};charset=utf8mb4", 'mode' => 'socket'];
    }

    // 環境変数でDBホストが指定されていれば最優先で試す（IP変更時のハードコード事故を防ぐ）
    if ($env_db_host !== '') {
        $candidates[] = ['dsn' => "mysql:host={$env_db_host};port={$env_db_port};dbname={$dbname};charset=utf8mb4", 'mode' => 'env_db_host'];
    }

    if ($in_container) {
        // コンテナ内ではホストの 127.0.0.1 はコンテナ自身なので、ホスト経由の接続候補を先に試す
        $candidates[] = ['dsn' => "mysql:host=host.docker.internal;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'host.docker.internal'];
        $candidates[] = ['dsn' => "mysql:host=172.17.0.1;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'docker_gateway'];
        $candidates[] = ['dsn' => "mysql:host=127.0.0.1;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => '127.0.0.1'];
        $candidates[] = ['dsn' => "mysql:host=localhost;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'localhost'];
    } else {
        // ホスト上での実行（例: ラズパイ本体）
        $candidates[] = ['dsn' => "mysql:host=127.0.0.1;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => '127.0.0.1'];
        $candidates[] = ['dsn' => "mysql:host=localhost;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'localhost'];
        $candidates[] = ['dsn' => "mysql:host=172.17.0.1;port=3306;dbname={$dbname};charset=utf8mb4", 'mode' => 'docker_gateway'];
    }
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
