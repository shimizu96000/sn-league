<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ユーザーアカウント定義（デフォルトパスワード）
$valid_users = [
    'guest' => [
        'password' => 'guest123',
        'role' => 'guest',      // 観戦者
        'permissions' => ['view_scores', 'view_player_profile']
    ],
    'player' => [
        'password' => 'player123',
        'role' => 'player',     // 選手
        'permissions' => ['view_scores', 'view_player_profile', 'submit_scores', 'edit_profile']
    ],
    'admin' => [
        'password' => 'admin1234',
        'role' => 'admin',      // 運営管理者
        'permissions' => ['view_scores', 'view_player_profile', 'submit_scores', 'edit_profile', 'manage_system']
    ]
];

// パスワード変更ファイルがあれば上書き（優先度: 変更されたパスワード > デフォルトパスワード）
$config_file = __DIR__ . '/data/user_passwords.json';
if (file_exists($config_file)) {
    $user_passwords = json_decode(file_get_contents($config_file), true) ?? [];
    foreach ($user_passwords as $username => $new_password) {
        if (isset($valid_users[$username])) {
            $valid_users[$username]['password'] = $new_password;
        }
    }
}

// ログイン試行制限の設定
$max_login_attempts = 5;                    // 最大試行回数
$lockout_duration = 15 * 60;                // ロック期間（秒）15分
$attempt_reset_duration = 1 * 60 * 60;      // 試行回数リセット期間（秒）1時間

// ログイン試行の履歴ファイル
$attempts_file = __DIR__ . '/data/login_attempts.json';
$attempts_data = [];

if (file_exists($attempts_file)) {
    $attempts_data = json_decode(file_get_contents($attempts_file), true) ?? [];
}

// データが古い試行を削除
$current_time = time();
foreach ($attempts_data as $username => $info) {
    // リセット期間を超過した場合、試行回数をリセット
    if ($current_time - $info['last_attempt'] > $attempt_reset_duration) {
        unset($attempts_data[$username]);
    }
}

$input_id = $_POST['username'] ?? '';
$input_password = $_POST['password'] ?? '';

// バリデーション
if (empty($input_id) || empty($input_password)) {
    $_SESSION['error_message'] = 'IDとパスワードを入力してください。';
    header('Location: login.php');
    exit();
}

// ログイン試行回数チェック
if (isset($attempts_data[$input_id])) {
    $user_attempts = $attempts_data[$input_id];
    
    // ロック中かどうか確認
    if ($user_attempts['locked_until'] > $current_time) {
        $remaining_minutes = ceil(($user_attempts['locked_until'] - $current_time) / 60);
        $_SESSION['error_message'] = "ログイン試行が多すぎます。{$remaining_minutes}分後にもう一度お試しください。";
        header('Location: login.php?redirect=' . urlencode($_POST['redirect'] ?? 'home.php'));
        exit();
    }
    
    // ロック期間が終了した場合、試行回数をリセット
    if ($user_attempts['locked_until'] <= $current_time) {
        $attempts_data[$input_id] = ['attempts' => 0, 'last_attempt' => $current_time, 'locked_until' => 0];
    }
} else {
    // 新規ユーザー
    $attempts_data[$input_id] = ['attempts' => 0, 'last_attempt' => $current_time, 'locked_until' => 0];
}

// ユーザー認証
if (isset($valid_users[$input_id]) && $valid_users[$input_id]['password'] === $input_password) {
    // 認証成功
    $_SESSION['is_logged_in'] = true;
    $_SESSION['username'] = $input_id;
    $_SESSION['role'] = $valid_users[$input_id]['role'];
    $_SESSION['permissions'] = $valid_users[$input_id]['permissions'];
    
    // ログイン試行回数をリセット
    unset($attempts_data[$input_id]);
    file_put_contents($attempts_file, json_encode($attempts_data, JSON_PRETTY_PRINT));
    
    // リダイレクト先を決定
    $redirect_url = $_POST['redirect'] ?? 'home.php';
    header('Location: ' . htmlspecialchars($redirect_url));
    exit();
} else {
    // 認証失敗
    // 試行回数をインクリメント
    $attempts_data[$input_id]['attempts']++;
    $attempts_data[$input_id]['last_attempt'] = $current_time;
    
    // 最大試行回数に達した場合、ロック
    if ($attempts_data[$input_id]['attempts'] >= $max_login_attempts) {
        $attempts_data[$input_id]['locked_until'] = $current_time + $lockout_duration;
        $_SESSION['error_message'] = "ログイン試行が多すぎます。15分後にもう一度お試しください。";
    } else {
        $remaining_attempts = $max_login_attempts - $attempts_data[$input_id]['attempts'];
        $_SESSION['error_message'] = "IDまたはパスワードが違います。（残り試行回数: {$remaining_attempts}回）";
    }
    
    // 試行を保存
    file_put_contents($attempts_file, json_encode($attempts_data, JSON_PRETTY_PRINT));
    
    header('Location: login.php?redirect=' . urlencode($_POST['redirect'] ?? 'home.php'));
    exit();
}
