<?php
require_once 'includes/init.php';
$page_title = '麻雀TIPS投稿';
$current_page = basename(__FILE__);

// TIPSの読み込み
$tips_file = 'tips_data.json';
$tips = [];
if (file_exists($tips_file)) {
    $tips = json_decode(file_get_contents($tips_file), true) ?? [];
}

// POSTされた場合の処理
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['content'])) {
        // 新規TIPSを追加（承認状態は未承認）
        $tips[] = [
            'id' => time(),
            'content' => $_POST['content'],
            'status' => 'pending', // pending=未承認, approved=承認済み
            'submitted_at' => date('Y-m-d H:i:s')
        ];
        
        // JSONファイルに保存
        file_put_contents($tips_file, json_encode($tips, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $message = 'TIPSを投稿しました。管理者の承認後に表示されます。';
    }
}

include 'includes/header.php';
?>

<h1>麻雀TIPS投稿</h1>

<div class="section">
    <h2>TIPSを投稿</h2>
    <?php if ($message): ?>
        <div class="message success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="tips-submission">
        <p class="submission-guide">
            麻雀の役や点数計算、ゲーム進行についての豆知識を投稿してください。<br>
            投稿されたTIPSは管理者の承認後にホーム画面で表示されます。
        </p>
        <form method="post" class="tips-form">
            <div class="form-group">
                <label for="tips-content">TIPSの内容:</label>
                <textarea id="tips-content" name="content" class="form-control" rows="3" required
                          placeholder="例：国士無双は役満です。"></textarea>
                <div class="form-help">※100文字程度で簡潔に入力してください</div>
            </div>
            <button type="submit" class="btn">投稿する</button>
        </form>
    </div>

    <div class="tips-examples">
        <h3>TIPSの例</h3>
        <ul>
            <li>カンしたらドラ表示牌が増えます。明槓も積極的に行いましょう。</li>
            <li>脳筋と特攻は違います。</li>
            <li>宇宙の法則によると、国士無双は7種から目指せます。</li>
        </ul>
    </div>
</div>

<script>
document.querySelector('.tips-form').addEventListener('submit', function(e) {
    const content = document.getElementById('tips-content').value;
    if (content.length > 200) {
        e.preventDefault();
        alert('TIPSの内容は200文字以内で入力してください。');
    }
});
</script>

<?php include 'includes/footer.php'; ?>