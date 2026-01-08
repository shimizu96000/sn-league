<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$admin_password = 'admin1234'; 
$input_password = $_POST['password'] ?? '';

if ($input_password === $admin_password) {
    // 認証成功
    $_SESSION['is_admin'] = true;
    header('Location: admin.php');
    exit();
} else {
    // 認証失敗
    $_SESSION['error_message'] = 'パスワードが違います。';
    header('Location: login.php');
    exit();
}
