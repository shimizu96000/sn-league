<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php';

// パスワード変更機能は無効です
// パスワードの変更は data/user_passwords.json を直接編集してください

http_response_code(403);
die('パスワード変更機能は無効です。パスワード変更は管理者に依頼してください。');
?>
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // バリデーション
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'すべてのフィールドを入力してください。';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = '新しいパスワードは6文字以上で設定してください。';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = '新しいパスワードと確認用パスワードが一致しません。';
        $message_type = 'error';
    } else {
        // パスワード定義（auth.phpと同じ）
        $valid_users = [
            'guest' => ['password' => 'guest123'],
            'player' => ['password' => 'player123'],
            'admin' => ['password' => 'admin1234'],
        ];
        
        // パスワード変更ファイルから現在のパスワードを読み込む
        $config_file = __DIR__ . '/data/user_passwords.json';
        if (file_exists($config_file)) {
            $user_passwords = json_decode(file_get_contents($config_file), true) ?? [];
            foreach ($user_passwords as $username => $pwd) {
                if (isset($valid_users[$username])) {
                    $valid_users[$username]['password'] = $pwd;
                }
            }
        }
        
        // 現在のパスワードが正しいか確認
        $username = $_SESSION['username'];
        $current_user_password = $valid_users[$username]['password'] ?? '';
        
        if ($current_user_password !== $current_password) {
            $message = '現在のパスワードが正しくありません。';
            $message_type = 'error';
        } else {
            // パスワード変更用の設定ファイルに保存
            $user_passwords = [];
            if (file_exists($config_file)) {
                $user_passwords = json_decode(file_get_contents($config_file), true) ?? [];
            }
            
            // 新しいパスワードを保存
            $user_passwords[$username] = $new_password;
            
            if (file_put_contents($config_file, json_encode($user_passwords, JSON_PRETTY_PRINT))) {
                $message = 'パスワードを変更しました。';
                $message_type = 'success';
            } else {
                $message = 'パスワードの保存に失敗しました。';
                $message_type = 'error';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    .password-form {
        max-width: 500px;
        margin: 30px auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #333;
    }
    
    .form-group input[type="password"] {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 16px;
        box-sizing: border-box;
    }
    
    .form-group input[type="password"]:focus {
        outline: none;
        border-color: #667eea;
    }
    
    .message {
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid;
    }
    
    .message.success {
        background: #e8f5e9;
        color: #2e7d32;
        border-left-color: #4caf50;
    }
    
    .message.error {
        background: #ffebee;
        color: #c62828;
        border-left-color: #f44336;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: #667eea;
        color: white;
    }
    
    .btn-primary:hover {
        background: #5568d3;
    }
    
    .btn-secondary {
        background: #e0e0e0;
        color: #333;
    }
    
    .btn-secondary:hover {
        background: #d0d0d0;
    }
    
    .user-info {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
    }
    
    .user-info strong {
        color: #667eea;
    }
    
    .password-hint {
        font-size: 12px;
        color: #999;
        margin-top: 5px;
    }
</style>

<div class="password-form">
    <h2 style="margin-top: 0; color: #333; border-bottom: 3px solid #667eea; padding-bottom: 15px;">パスワード変更</h2>
    
    <div class="user-info">
        <strong>ユーザー:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?> 
        <span style="font-size: 12px; color: #999;">(<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="current_password">現在のパスワード</label>
            <input type="password" id="current_password" name="current_password" required>
            <div class="password-hint">現在お使いのパスワードを入力してください</div>
        </div>
        
        <div class="form-group">
            <label for="new_password">新しいパスワード</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">
            <div class="password-hint">6文字以上で設定してください</div>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">新しいパスワード（確認）</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            <div class="password-hint">同じパスワードを入力してください</div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">パスワードを変更</button>
            <a href="home.php" class="btn btn-secondary" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">キャンセル</a>
        </div>
    </form>
    
    <div style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-radius: 6px;">
        <strong style="color: #333;">注意事項</strong>
        <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #666; font-size: 13px;">
            <li>パスワードはセキュアに管理されます</li>
            <li>強力なパスワード設定を推奨します</li>
            <li>パスワード変更後は新しいパスワードでログインしてください</li>
        </ul>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
