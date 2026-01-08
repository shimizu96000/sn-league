<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); // エラーメッセージは一度表示したら消す
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ログイン - SNリーグ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>管理者ログイン</h1>
        <form action="auth.php" method="post" class="login-form">
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <div class="form-group">
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <input type="submit" value="ログイン" class="submit-btn">
        </form>
    </div>
</body>
</html>