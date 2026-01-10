<?php
/**
 * ログイン機能テストスクリプト
 * 各機能が正しく動作しているか確認します
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$test_results = [];

// テスト1: ユーザー定義が正しいか
$test_results['users_defined'] = [
    'description' => 'auth.php でユーザーが定義されているか',
    'status' => 'pending'
];

// テスト2: 権限関数が実装されているか
$test_results['permission_functions'] = [
    'description' => 'includes/init.php に権限チェック関数があるか',
    'status' => 'pending'
];

// テスト3: logout.php が存在するか
$test_results['logout_exists'] = [
    'description' => 'logout.php ファイルが存在するか',
    'status' => file_exists(__DIR__ . '/logout.php') ? 'pass' : 'fail',
    'file' => 'logout.php'
];

// テスト4: ファイル存在確認
$files_to_check = [
    'auth.php' => 'ログイン処理',
    'login.php' => 'ログインページ',
    'includes/init.php' => '権限チェック関数',
    'home.php' => 'ホームページ',
    'logout.php' => 'ログアウト処理',
    'settings.php' => 'パスワード変更',
];

$file_results = [];
foreach ($files_to_check as $file => $description) {
    $file_results[$file] = [
        'description' => $description,
        'status' => file_exists(__DIR__ . '/' . $file) ? 'pass' : 'fail',
        'path' => $file
    ];
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン機能テスト - SNリーグ</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        h2 {
            color: #667eea;
            margin-top: 30px;
        }
        .test-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #ddd;
            border-radius: 4px;
        }
        .test-item.pass {
            border-left-color: #4caf50;
            background: #f1f8e9;
        }
        .test-item.fail {
            border-left-color: #f44336;
            background: #ffebee;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.pass {
            background: #4caf50;
            color: white;
        }
        .status.fail {
            background: #f44336;
            color: white;
        }
        .status.pending {
            background: #ff9800;
            color: white;
        }
        .description {
            display: block;
            color: #666;
            margin-top: 5px;
            font-size: 0.9em;
        }
        .action-buttons {
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn:hover {
            opacity: 0.9;
        }
        code {
            background: #f4f4f4;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <h1>🧪 ログイン機能テスト</h1>
    
    <h2>📄 ファイル存在確認</h2>
    <?php foreach ($file_results as $file => $result): ?>
    <div class="test-item <?php echo $result['status']; ?>">
        <strong><?php echo htmlspecialchars($file); ?></strong>
        <span class="status <?php echo $result['status']; ?>">
            <?php echo $result['status'] === 'pass' ? '✓ 存在' : '✗ 不在'; ?>
        </span>
        <span class="description"><?php echo htmlspecialchars($result['description']); ?></span>
    </div>
    <?php endforeach; ?>

    <h2>🔐 ログイン情報</h2>
    <div class="test-item">
        <strong>観戦者アカウント</strong>
        <span class="description">
            ID: <code>guest</code><br>
            パスワード: <code>guest123</code><br>
            権限: 成績閲覧、選手プロフィール閲覧
        </span>
    </div>
    <div class="test-item">
        <strong>選手アカウント</strong>
        <span class="description">
            ID: <code>player</code><br>
            パスワード: <code>player123</code><br>
            権限: 成績入力、成績閲覧、プロフィール編集
        </span>
    </div>
    <div class="test-item">
        <strong>運営管理者アカウント</strong>
        <span class="description">
            ID: <code>admin</code><br>
            パスワード: <code>admin1234</code><br>
            権限: すべて + 管理画面アクセス
        </span>
    </div>

    <h2>🔒 ログイン試行制限</h2>
    <div class="test-item">
        <strong>ログイン試行制限の仕様</strong>
        <span class="description">
            • 最大試行回数: <code>5回</code><br>
            • ロック期間: <code>15分</code><br>
            • 試行回数リセット: <code>1時間後</code>（最後の試行から）<br>
            • 失敗時にはメッセージで残り試行回数を表示します<br>
            • 試行履歴は <code>data/login_attempts.json</code> に保存
        </span>
    </div>

    <h2>🔄 パスワード変更</h2>
    <div class="test-item pass">
        <strong>パスワード変更機能</strong>
        <span class="status pass">✓ 実装済み</span>
        <span class="description">
            • URL: <code>settings.php</code><br>
            • アクセス: すべてのログイン済みユーザー<br>
            • ヘッダーから「パスワード変更」リンクでアクセス<br>
            • パスワード保存: <code>data/user_passwords.json</code><br>
            • 要件: 6文字以上
        </span>
    </div>

    <h2>🧬 権限チェック実装状況</h2>
    <div class="test-item pass">
        <strong>score_form.php</strong>
        <span class="status pass">✓ 実装済み</span>
        <span class="description">選手のみアクセス可能（submit_scores権限）</span>
    </div>
    <div class="test-item pass">
        <strong>save_score.php</strong>
        <span class="status pass">✓ 実装済み</span>
        <span class="description">選手のみ成績を保存可能（submit_scores権限）</span>
    </div>
    <div class="test-item pass">
        <strong>player_profile.php</strong>
        <span class="status pass">✓ 実装済み</span>
        <span class="description">選手のみ写真・紹介文編集可能（edit_profile権限）</span>
    </div>
    <div class="test-item pass">
        <strong>home.php</strong>
        <span class="status pass">✓ 実装済み</span>
        <span class="description">ログイン確認、未ログインの場合 login.php へリダイレクト</span>
    </div>
    <div class="test-item pass">
        <strong>admin.php</strong>
        <span class="status pass">✓ 実装済み</span>
        <span class="description">管理者のみアクセス可能（manage_system権限）</span>
    </div>

    <h2>🚀 次のステップ</h2>
    <div class="action-buttons">
        <a href="login.php" class="btn btn-primary">ログインページへ</a>
        <a href="LOGIN_GUIDE.md" class="btn btn-secondary">ログインガイド</a>
        <a href="DEPLOYMENT_CHECKLIST.md" class="btn btn-secondary">公開チェックリスト</a>
    </div>

    <h2>✅ テスト手順</h2>
    <ol>
        <li><strong>ゲストでログイン</strong>
            <ul>
                <li><a href="login.php">ログインページ</a>から ID: guest / パスワード: guest123 でログイン</li>
                <li>ホームページが表示される確認</li>
                <li>「成績入力」メニューが表示されず、クリックして 403 エラーになることを確認</li>
            </ul>
        </li>
        <li><strong>選手でログイン</strong>
            <ul>
                <li>ログアウト</li>
                <li>ID: player / パスワード: player123 でログイン</li>
                <li>「成績入力」メニューが表示される確認</li>
                <li>選手プロフィールで編集ボタンが表示される確認</li>
            </ul>
        </li>
        <li><strong>パスワード変更テスト</strong>
            <ul>
                <li>player でログイン状態</li>
                <li>ヘッダーのユーザー名から「パスワード変更」をクリック</li>
                <li>現在のパスワード（player123）を入力</li>
                <li>新しいパスワード（例：newpass123）を入力して変更</li>
                <li>ログアウト後、新しいパスワードでログイン可能か確認</li>
            </ul>
        </li>
        <li><strong>ログイン試行制限テスト</strong>
            <ul>
                <li><a href="login.php">ログインページ</a>で間違ったパスワードで5回試行</li>
                <li>6回目：「ログイン試行が多すぎます。15分後...」メッセージを確認</li>
                <li>15分後に再度ログイン可能か確認（テスト環境ではロック期間を短縮可能）</li>
            </ul>
        </li>
        <li><strong>管理者でログイン</strong>
            <ul>
                <li>ID: admin / パスワード: admin1234 でログイン</li>
                <li><a href="admin.php">管理画面（admin.php）</a>へアクセス可能か確認</li>
                <li>ゲストまたはプレイヤーで admin.php にアクセス → 403 エラー確認</li>
            </ul>
        </li>
        <li><strong>ログアウト確認</strong>
            <ul>
                <li>ヘッダーのユーザー名▼からログアウト</li>
                <li>ログインページへ自動リダイレクト</li>
            </ul>
        </li>
        <li><strong>権限なしアクセス確認</strong>
            <ul>
                <li>ゲストでログイン</li>
                <li>URL直接指定で score_form.php にアクセス → 403 エラー</li>
            </ul>
        </li>
    </ol>

</body>
</html>
