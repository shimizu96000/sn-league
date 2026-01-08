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