<?php
// 管理画面 - クイズ管理
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php';

// 管理権限が無ければログインへ
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

$page_title = 'クイズ管理';
$current_page = 'admin_quiz.php';

// クイズデータの読み込み
$quiz_file = __DIR__ . '/data/quiz_data.json';
$quizzes = [];
if (file_exists($quiz_file)) {
    $quizzes = json_decode(file_get_contents($quiz_file), true) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_quiz' && isset($_POST['quiz'])) {
        $quiz = $_POST['quiz'];
        if (!empty($quiz['question']) && !empty($quiz['options']) && isset($quiz['answer'])) {
            // process options and per-option explanations
            $rawOptions = isset($quiz['options']) && is_array($quiz['options']) ? $quiz['options'] : [];
            $rawExps = isset($quiz['explanations']) && is_array($quiz['explanations']) ? $quiz['explanations'] : [];
            $options = [];
            $explanations = [];
            foreach ($rawOptions as $i => $opt) {
                $opt = trim($opt);
                if ($opt !== '') {
                    $options[] = $opt;
                    $explanations[] = isset($rawExps[$i]) ? $rawExps[$i] : '';
                }
            }
            $answerIndex = (int)$quiz['answer'];
            if ($answerIndex < 0 || $answerIndex >= count($options)) $answerIndex = 0;
            $entry = [
                'question' => $quiz['question'],
                'options' => array_values($options),
                'answer' => $answerIndex,
                'explanation' => $quiz['explanation']
            ];
            // include per-option explanations if any non-empty
            $hasPer = false;
            foreach ($explanations as $e) { if (trim($e) !== '') { $hasPer = true; break; } }
            if ($hasPer) $entry['explanations'] = array_values($explanations);

            $quizzes[] = $entry;
            file_put_contents($quiz_file, json_encode($quizzes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo '<p class="status-public">クイズを追加しました。</p>';
        }
    } elseif ($_POST['action'] === 'delete_quiz' && isset($_POST['index'])) {
        $index = (int)$_POST['index'];
        if (isset($quizzes[$index])) {
            array_splice($quizzes, $index, 1);
            file_put_contents($quiz_file, json_encode($quizzes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo '<p class="status-public">クイズを削除しました。</p>';
        }
    } elseif ($_POST['action'] === 'edit_quiz' && isset($_POST['index']) && isset($_POST['quiz'])) {
        $index = (int)$_POST['index'];
        $quizInput = $_POST['quiz'];
        if (isset($quizzes[$index]) && !empty($quizInput['question']) && !empty($quizInput['options']) && isset($quizInput['answer'])) {
            $rawOptions = is_array($quizInput['options']) ? $quizInput['options'] : [];
            $rawExps = is_array($quizInput['explanations']) ? $quizInput['explanations'] : [];
            $options = [];
            $explanations = [];
            foreach ($rawOptions as $i => $opt) {
                $opt = trim($opt);
                if ($opt !== '') {
                    $options[] = $opt;
                    $explanations[] = isset($rawExps[$i]) ? $rawExps[$i] : '';
                }
            }
            $answerIndex = (int)$quizInput['answer'];
            if ($answerIndex < 0 || $answerIndex >= count($options)) $answerIndex = 0;
            $entry = [
                'question' => $quizInput['question'],
                'options' => array_values($options),
                'answer' => $answerIndex,
                'explanation' => $quizInput['explanation'] ?? ''
            ];
            $hasPer = false;
            foreach ($explanations as $e) { if (trim($e) !== '') { $hasPer = true; break; } }
            if ($hasPer) $entry['explanations'] = array_values($explanations);

            // replace the quiz at index
            $quizzes[$index] = $entry;
            file_put_contents($quiz_file, json_encode($quizzes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo '<p class="status-public">クイズを更新しました。</p>';
        }
    }
}

include 'includes/header.php';
?>
    <h1>麻雀クイズ管理</h1>
    <div class="quiz-manager">
        <p><a href="admin.php" class="btn">管理画面に戻る</a></p>

        <h3>新規クイズの追加</h3>
        <form method="POST" class="quiz-form">
            <input type="hidden" name="action" value="add_quiz">
            <div class="form-group">
                <label>問題文:</label>
                <textarea name="quiz[question]" required rows="3" class="form-control"></textarea>
                <div class="markdown-help">
                    <p>麻雀牌の表示方法:</p>
                    <ul>
                        <li><code>[m123]</code> → 一萬・二萬・三萬</li>
                        <li><code>[p456]</code> → 四筒・五筒・六筒</li>
                        <li><code>[s789]</code> → 七索・八索・九索</li>
                        <li><code>[z1234]</code> → 東・南・西・北</li>
                        <li><code>[z567]</code> → 白・發・中</li>
                    </ul>
                    <p>例：<code>この手牌 [m123][p456][s789] の待ちは？</code></p>
                </div>
            </div>
            <div class="form-group">
                <label>選択肢 (空白は無視されます):</label>
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <input type="text" name="quiz[options][]" class="form-control" placeholder="選択肢<?php echo $i + 1; ?>">
                    <input type="text" name="quiz[explanations][]" class="form-control" placeholder="選択肢<?php echo $i + 1; ?> の解説 (任意)" style="margin-top:6px;">
                <?php endfor; ?>
            </div>
            <div class="form-group">
                <label>正解の選択肢番号 (0から始まる):</label>
                <input type="number" name="quiz[answer]" required min="0" max="3" class="form-control">
            </div>
            <div class="form-group">
                <label>解説:</label>
                <textarea name="quiz[explanation]" required rows="3" class="form-control"></textarea>
            </div>
            <button type="submit" class="btn">クイズを追加</button>
        </form>

        <h3 style="margin-top:20px;">既存のクイズ一覧</h3>
        <div class="quiz-list">
            <?php foreach ($quizzes as $index => $quiz): ?>
                <div class="quiz-item">
                    <h4>問題 <?php echo $index + 1; ?></h4>
                    <p><strong>問題文:</strong> <?php echo htmlspecialchars($quiz['question']); ?></p>
                    <p><strong>選択肢:</strong></p>
                    <ul>
                        <?php foreach ($quiz['options'] as $i => $option): ?>
                            <li><?php echo $i === $quiz['answer'] ? '<strong>' : ''; ?>
                                <?php echo htmlspecialchars($option); ?>
                                <?php echo $i === $quiz['answer'] ? '</strong> (正解)' : ''; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p><strong>解説:</strong> <?php echo nl2br(htmlspecialchars($quiz['explanation'])); ?></p>
                    <?php if (isset($quiz['explanations']) && is_array($quiz['explanations'])): ?>
                        <p><strong>選択肢ごとの解説:</strong></p>
                        <ul>
                        <?php foreach ($quiz['options'] as $i => $option): ?>
                            <li>
                                <?php echo htmlspecialchars($option); ?>
                                <?php if (isset($quiz['explanations'][$i]) && trim($quiz['explanations'][$i]) !== ''): ?>
                                    <div style="margin-left:8px; color:#333;">解説: <?php echo nl2br(htmlspecialchars($quiz['explanations'][$i])); ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete_quiz">
                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                        <button type="submit" class="btn btn-external" onclick="return confirm('このクイズを削除してもよろしいですか？')">削除</button>
                    </form>
                    <button type="button" class="btn" onclick="document.getElementById('edit-form-<?php echo $index; ?>').style.display='block'; this.style.display='none';">編集</button>
                    <div id="edit-form-<?php echo $index; ?>" style="display:none; margin-top:12px; padding:8px; border:1px solid #ddd; background:#fafafa;">
                        <form method="POST" class="quiz-form">
                            <input type="hidden" name="action" value="edit_quiz">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <div class="form-group">
                                <label>問題文:</label>
                                <textarea name="quiz[question]" required rows="3" class="form-control"><?php echo htmlspecialchars($quiz['question']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>選択肢 (空白は無視されます):</label>
                                <?php for ($i = 0; $i < 4; $i++): ?>
                                    <input type="text" name="quiz[options][]" class="form-control" placeholder="選択肢<?php echo $i + 1; ?>" value="<?php echo htmlspecialchars($quiz['options'][$i] ?? ''); ?>">
                                    <input type="text" name="quiz[explanations][]" class="form-control" placeholder="選択肢<?php echo $i + 1; ?> の解説 (任意)" style="margin-top:6px;" value="<?php echo htmlspecialchars($quiz['explanations'][$i] ?? ''); ?>">
                                <?php endfor; ?>
                            </div>
                            <div class="form-group">
                                <label>正解の選択肢番号 (0から始まる):</label>
                                <input type="number" name="quiz[answer]" required min="0" max="3" class="form-control" value="<?php echo (int)$quiz['answer']; ?>">
                            </div>
                            <div class="form-group">
                                <label>解説:</label>
                                <textarea name="quiz[explanation]" required rows="3" class="form-control"><?php echo htmlspecialchars($quiz['explanation'] ?? ''); ?></textarea>
                            </div>
                            <div style="margin-top:8px;">
                                <button type="submit" class="btn">保存</button>
                                <button type="button" class="btn" onclick="document.getElementById('edit-form-<?php echo $index; ?>').style.display='none'; document.querySelector('#edit-form-<?php echo $index; ?>').previousElementSibling.style.display='inline-block';">キャンセル</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>