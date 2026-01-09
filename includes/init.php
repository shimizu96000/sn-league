<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$maintenance_file = __DIR__ . '/../data/maintenance_status.txt';
$is_maintenance = file_exists($maintenance_file) && file_get_contents($maintenance_file) === '1';

// メンテナンス中で、かつ管理者としてログインしていない場合
if ($is_maintenance && (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true)) {
    // メンテナンスページにリダイレクト
    header('Location: maintenance.php');
    exit();
}

/**
 * 権限をチェック
 * @param string|array $required_permission 必要な権限
 * @param bool $redirect リダイレクトするかどうか
 * @return bool 権限があるかどうか
 */
function check_permission($required_permission, $redirect = true) {
    if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
        if ($redirect) {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit();
        }
        return false;
    }
    
    $permissions = $_SESSION['permissions'] ?? [];
    
    if (is_array($required_permission)) {
        // 複数の権限のいずれかを持っているか
        foreach ($required_permission as $perm) {
            if (in_array($perm, $permissions)) {
                return true;
            }
        }
        return false;
    } else {
        // 単一の権限をチェック
        if (!in_array($required_permission, $permissions)) {
            if ($redirect) {
                http_response_code(403);
                die('アクセス権限がありません。');
            }
            return false;
        }
        return true;
    }
}

/**
 * ユーザーがログインしているか確認
 * @param bool $redirect リダイレクトするかどうか
 * @return bool ログイン状態
 */
function is_user_logged_in($redirect = true) {
    if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
        if ($redirect) {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit();
        }
        return false;
    }
    return true;
}

/**
 * ユーザーのロールを取得
 * @return string ロール（guest, player, など）
 */
function get_user_role() {
    return $_SESSION['role'] ?? 'guest';
}

/**
 * ユーザーのIDを取得
 * @return string ユーザー ID
 */
function get_username() {
    return $_SESSION['username'] ?? 'guest';
}