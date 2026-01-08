<?php
require_once 'includes/init.php';
$page_title = '麻雀TIPS管理';
$current_page = basename(__FILE__);
include 'includes/header.php';

// 管理権限が無ければログインへ
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

// TIPSの読み込み
$tips_file = 'tips_data.json';
$tips = [];
if (file_exists($tips_file)) {
    $tips = json_decode(file_get_contents($tips_file), true) ?? [];
}

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // 新規TIPS追加
            if (!empty($_POST['content'])) {
                array_unshift($tips, [
                    'id' => time(), // UNIXタイムスタンプをIDとして使用
                    'content' => $_POST['content'],
                    'status' => 'pending',
                    'submitted_at' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'edit':
            // TIPS編集
            if (isset($_POST['id']) && isset($_POST['content'])) {
                $id = (int)$_POST['id'];
                foreach ($tips as &$tip) {
                    if ($tip['id'] === $id) {
                        $tip['content'] = $_POST['content'];
                        $tip['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
            }
            break;
            
        case 'delete':
            // TIPS削除
            if (isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $tips = array_filter($tips, function($tip) use ($id) {
                    return $tip['id'] !== $id;
                });
            }
            break;

        case 'change_status':
            // ステータス変更（承認/却下）
            if (isset($_POST['id']) && isset($_POST['status'])) {
                $id = (int)$_POST['id'];
                $newStatus = $_POST['status'];
                if (in_array($newStatus, ['pending', 'approved', 'rejected'])) {
                    foreach ($tips as &$tip) {
                        if ($tip['id'] === $id) {
                            $tip['status'] = $newStatus;
                            $tip['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                }
            }
            break;
    }
    
    // JSON保存
    file_put_contents($tips_file, json_encode($tips, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // リダイレクトして再読み込み
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

?>

<h1>麻雀TIPS管理</h1>

<div class="section">
    <p><a href="admin.php" class="btn">管理画面に戻る</a></p>

    <h2>新規TIPS追加</h2>
    <form method="post" class="tips-form">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label for="new-tip">TIPSの内容:</label>
            <textarea id="new-tip" name="content" class="form-control" rows="3" required></textarea>
            <div class="form-help">※100文字程度で簡潔に入力してください</div>
        </div>
        <button type="submit" class="btn btn-primary">追加</button>
    </form>
</div>

<div class="section">
    <h2>登録済みTIPS一覧</h2>
    <div class="tips-list">
        <?php if (empty($tips)): ?>
            <p>登録されているTIPSはありません。</p>
        <?php else: ?>
            <?php foreach ($tips as $tip): ?>
                <div class="tips-item <?php echo htmlspecialchars($tip['status'] ?? 'pending'); ?>" data-id="<?php echo $tip['id']; ?>">
                    <div class="tips-meta">
                        <?php if (isset($tip['submitted_at'])): ?>
                            登録: <?php echo htmlspecialchars($tip['submitted_at']); ?>
                        <?php endif; ?>
                        <?php if (isset($tip['updated_at'])): ?>
                            | 更新: <?php echo htmlspecialchars($tip['updated_at']); ?>
                        <?php endif; ?>
                        | ステータス: <?php echo htmlspecialchars($tip['status'] ?? 'pending'); ?>
                    </div>
                    <div class="tips-content"><?php echo nl2br(htmlspecialchars($tip['content'])); ?></div>
                    <div class="tips-actions">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="id" value="<?php echo $tip['id']; ?>">
                            <input type="hidden" name="status" value="approved">
                            <button type="submit" class="btn-external approve">承認</button>
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="id" value="<?php echo $tip['id']; ?>">
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="btn-external reject">却下</button>
                        </form>
                        <button type="button" class="btn-external edit edit-tip">編集</button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $tip['id']; ?>">
                            <button type="submit" class="btn-external reject delete-tip" onclick="return confirm('このTIPSを削除してもよろしいですか？');">削除</button>
                        </form>
                    </div>
                    <form method="post" class="edit-form" style="display:none;">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $tip['id']; ?>">
                        <textarea name="content" class="form-control" rows="3" required><?php echo htmlspecialchars($tip['content']); ?></textarea>
                        <div class="form-actions">
                            <button type="submit" class="btn-external">保存</button>
                            <button type="button" class="btn-external cancel-edit">キャンセル</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 編集ボタンのイベントハンドラ
    document.querySelectorAll('.edit-tip').forEach(button => {
        button.addEventListener('click', function() {
            const item = this.closest('.tips-item');
            item.querySelector('.tips-content').style.display = 'none';
            item.querySelector('.tips-actions').style.display = 'none';
            item.querySelector('.edit-form').style.display = 'block';
        });
    });
    
    // キャンセルボタンのイベントハンドラ
    document.querySelectorAll('.cancel-edit').forEach(button => {
        button.addEventListener('click', function() {
            const item = this.closest('.tips-item');
            item.querySelector('.tips-content').style.display = 'block';
            item.querySelector('.tips-actions').style.display = 'block';
            item.querySelector('.edit-form').style.display = 'none';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>