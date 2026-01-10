# SNリーグ公開準備 - 実装ドキュメント

## ✅ 実装済み機能

### 1. ログイン機能
- **観戦者アカウント**
  - ID: `guest`
  - パスワード: `guest123`
  - 権限: 成績閲覧、選手プロフィール閲覧

- **選手アカウント**
  - ID: `player`
  - パスワード: `player123`
  - 権限: 成績入力、成績閲覧、選手プロフィール編集（写真・紹介文）

- **運営管理者アカウント**
  - ID: `admin`
  - パスワード: `admin1234`
  - 権限: すべての機能 + 管理画面（admin.php）

### 2. ログイン試行制限
- **最大試行回数:** 5回
- **ロック期間:** 15分
- **試行回数リセット:** 1時間後（最後の試行から）
- **履歴保存:** `data/login_attempts.json`

### 3. パスワード変更機能
- **URL:** `settings.php`
- **アクセス:** すべてのログイン済みユーザー
- **パスワード要件:** 6文字以上
- **保存先:** `data/user_passwords.json`
- **更新UI:** ヘッダーのユーザー名から「パスワード変更」リンク

### 4. 権限管理
- `includes/init.php` に権限チェック関数を実装
  - `check_permission($permission, $redirect=true)` - 権限チェック
  - `is_user_logged_in($redirect=true)` - ログイン確認
  - `get_user_role()` - ユーザーロール取得
  - `get_username()` - ユーザーID取得

### 5. アクセス制限が実装されたページ

| ページ | 制限内容 | 許可ロール |
|--------|--------|----------|
| `score_form.php` | 成績入力 | player |
| `save_score.php` | 成績保存 | player |
| `player_profile.php` | 写真アップロード | player |
| `player_profile.php` | 紹介文編集UI | player |
| `player_api.php` | 紹介文更新API | player |
| `player_api.php` | コメント削除 | player |
| `admin.php` | 管理画面 | admin |

### 6. UI/UX改善
- ログインページのデザイン改善
- ヘッダーにログイン情報表示（ユーザーID、ロール）
- ヘッダーにパスワード変更・ログアウトメニュー
- ログアウト機能（`logout.php`）

### 7. パスワード保存場所
**デフォルトパスワード（変更不可）:**
- ファイル: `auth.php`
- 内容: `$valid_users` 配列で定義
- guest: `guest123`
- player: `player123`
- admin: `admin1234`

**変更後のパスワード:**
- ファイル: `data/user_passwords.json`
- 形式: JSON（キー: ユーザーID、値: 新しいパスワード）
- 例: `{"guest": "newpassword123", "player": "mypassword456"}`
- 動作: `auth.php` でデフォルトパスワードを読み込み、このファイルがあれば上書き

**認証時の処理フロー:**
1. `auth.php` で `$valid_users` からデフォルトパスワードを読み込む
2. `data/user_passwords.json` が存在する場合、変更後のパスワードで上書き
3. ユーザー入力のパスワードと照合

## 📋 公開前のチェックリスト

### セキュリティ確認
- [ ] データベース接続情報の確認（root/空パスワード は本番環境で変更必須）
- [ ] HTTPS設定（本番環境で必須）
- [ ] パスワード保存ファイル（data/user_passwords.json）のアクセス制限
- [ ] ログイン試行履歴ファイル（data/login_attempts.json）のアクセス制限
- [ ] CSRF対策トークンの実装（必要に応じて）
- [ ] SQL インジェクション対策：PDO準備済みステートメント使用済み ✓
- [ ] XSS対策：htmlspecialchars使用済み ✓

### 機能確認
- [ ] ログイン機能のテスト（guest/player/admin全て）
- [ ] ログイン試行制限のテスト
- [ ] パスワード変更のテスト
- [ ] 権限チェックのテスト（guest が score_form にアクセスで403）
- [ ] 権限チェックのテスト（guest が score_form にアクセスで403）
- [ ] ログアウト機能のテスト
- [ ] 各ページでのログイン必須確認（home.php など）

### データベース確認
- [ ] `results` テーブルが存在するか確認
- [ ] データベース構造確認：
  ```sql
  DESCRIBE results;
  ```

### ファイル確認
- [ ] `data/cache_players.json` が存在するか
- [ ] `data/player_images.json` が存在するか
- [ ] `data/player_intro.json` が存在するか
- [ ] `data/player_comments.json` が存在するか
- [ ] `player_images/` ディレクトリが存在するか
- [ ] `data/user_passwords.json` が作成されたか
- [ ] `data/login_attempts.json` が作成されたか

## 🔧 その他実装が必要な機能（将来的に）

### 1. セッション管理強化（推奨）
```php
// 推奨設定（php.ini または ページの先頭で）
ini_set('session.gc_maxlifetime', 3600);        // 1時間
ini_set('session.cookie_lifetime', 3600);       // 1時間
ini_set('session.cookie_httponly', 1);          // HTTPのみ
ini_set('session.cookie_secure', 1);            // HTTPS only (本番環境)
```

### 2. パスワード管理強化（本番環境では必須）
- 現在：明白なパスワード保存（テスト用）
- 本番環境：`password_hash()` と `password_verify()` を使用

**実装例：**
```php
// パスワード保存時
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

// パスワード検証時
if (password_verify($input_password, $hashed_password)) {
    // パスワード一致
}
```

### 3. ログ記録・監査
- ログイン/ログアウトの記録
- 権限なしアクセス試行の記録
- ログイン試行失敗の記録（現在は JSON保存）
- データ更新操作の監査ログ

### 4. アカウント管理画面（将来実装）
- ユーザーの追加/削除/編集
- パスワードリセット機能
- ロール管理

## 📝 使用方法

### ログイン
1. ページにアクセス → `login.php` にリダイレクト
2. ID/パスワード入力
3. 権限に応じたページに自動リダイレクト

### パスワード変更
1. ログイン後、ヘッダーのユーザー名をクリック
2. 「パスワード変更」を選択
3. 現在のパスワードと新しいパスワードを入力

### ログアウト
- ヘッダーの右上「ユーザー名▼」をクリック
- 「ログアウト」を選択

### 権限チェック例
```php
// ページ上部で権限チェック
require_once 'includes/init.php';
check_permission('submit_scores');  // 成績入力権限が必要

// または権限を確認してUIを変更
if (check_permission('edit_profile', false)) {
    // 選手用UI を表示
    echo '<button>編集</button>';
}

// または権限を確認してユーザー情報を取得
if (is_user_logged_in(false)) {
    $username = get_username();
    $role = get_user_role();
}
```

## 🚀 本番環境へのデプロイ前の作業

1. **セキュリティ対策**
   - データベースユーザー/パスワード変更
   - HTTPS有効化
   - セッション設定強化
   - パスワードハッシュ化の実装
   - 試行回数制限値の見直し

2. **バックアップ**
   - データベース完全バックアップ
   - 重要ファイル（JSON ファイル）のバックアップ

3. **テスト**
   - 全ロールでの動作確認
   - 権限チェック動作確認
   - 各エラーシーン対応確認

4. **ドキュメント**
   - ユーザーマニュアル準備
   - トラブルシューティングガイド

## 📧 質問・要望

公開前に以下の点について確認が必要です：

1. **管理者ロールが必要か？** - 運営者向けの管理画面
2. **パスワード変更機能は必要か？**
3. **ユーザー追加/削除機能は必要か？**
4. **ログイン試行回数制限（ブルートフォース対策）は必要か？**
5. **セッションタイムアウト時間の希望は？**
