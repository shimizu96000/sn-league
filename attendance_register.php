<?php
require_once 'includes/init.php';
$page_title = '出欠登録';
$current_page = basename(__FILE__);
// プレイヤー候補を cache_players.json から読み込み
$players = [];
$players_file = __DIR__ . '/data/cache_players.json';
if (file_exists($players_file)) {
    $players = json_decode(file_get_contents($players_file), true) ?? [];
}
include 'includes/header.php';
?>
    <h1>出欠登録（単独ページ）</h1>
    <div class="section">
        <form method="post" action="update_attendance.php">
            <div class="form-group">
                <label for="date-input">日付</label>
                <input id="date-input" name="dates" type="date" required />
                <p class="catchphrase-small">複数日を一度に登録する場合はカンマ区切りで Y-n-j 形式（例: 2025-10-01）を入力してください。</p>
            </div>
            <div class="form-group">
                <label for="player-select">選手</label>
                <select id="player-select" name="player" required>
                    <?php foreach ($players as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group status-options">
                <label><input type="radio" name="status" value="参加" checked> 参加</label>
                <label><input type="radio" name="status" value="不参加"> 不参加</label>
                <label><input type="radio" name="status" value="未定"> 未定</label>
            </div>
            <div style="text-align:right; margin-top:12px;">
                <button class="btn" type="submit">登録</button>
            </div>
        </form>
    </div>
<?php include 'includes/footer.php';
?>