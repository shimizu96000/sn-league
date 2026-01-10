<?php
session_start();
require_once 'includes/init.php';

// ログイン処理
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    // パスワードチェック（実際の運用時はハッシュ化したパスワードを使用）
    if ($password === 'snl-admin-2024') { // この値は実運用時に変更してください
        $_SESSION['admin_authenticated'] = true;
        
        // リダイレクト先を決定
        $redirect = 'admin.php';
        if (isset($_GET['redirect'])) {
            $allowed_redirects = ['admin.php', 'admin_tips.php'];
            if (in_array($_GET['redirect'], $allowed_redirects)) {
                $redirect = $_GET['redirect'];
            }
        }
        
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'パスワードが正しくありません。';
    }
}

$page_title = '管理者ログイン';
include 'includes/header.php';
?>

<h1>管理者ログイン</h1>

<div class="section">
    <div class="login-container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post" class="login-form">
            <div class="form-group">
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn">ログイン</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>