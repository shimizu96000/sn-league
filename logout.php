<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// セッションの全変数を削除
$_SESSION = [];

// セッションクッキーを削除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破壊
session_destroy();

// ログインページにリダイレクト
header('Location: login.php');
exit();
