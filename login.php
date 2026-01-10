<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン済みならホームページへ
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']) {
    header('Location: home.php');
    exit();
}

$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); // エラーメッセージは一度表示したら消す

$redirect = $_GET['redirect'] ?? 'home.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - SNリーグ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        .login-form {
            display: flex;
            flex-direction: column;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .info-box {
            background-color: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-top: 30px;
            border-radius: 5px;
            font-size: 13px;
            color: #666;
        }
        .info-box h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .user-type {
            margin-bottom: 8px;
        }
        .user-type strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SNリーグ</h1>
        <p class="subtitle">ログイン</p>

        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="auth.php" method="post" class="login-form">
            <div class="form-group">
                <label for="username">ID:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            <input type="submit" value="ログイン" class="submit-btn">
        </form>
    </div>
</body>
</html>
