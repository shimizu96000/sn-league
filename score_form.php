<?php
$page_title = '成績入力';
$current_page = basename(__FILE__);
require_once 'includes/init.php';

// デバッグ情報を表示する関数
function debug_log($message) {
    error_log($message);
    echo "<!-- Debug: " . htmlspecialchars($message) . " -->\n";
}

// scores.csvからプレイヤーリストを生成
$participants = [];
$scores_file = __DIR__ . '/data/scores.csv';

debug_log("Starting player list generation...");

if (file_exists($scores_file)) {
    if (($handle = fopen($scores_file, "r")) !== FALSE) {
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 13) {
                foreach ([1, 4, 7, 10] as $index) {
                    if (isset($data[$index])) {
                        $name = trim($data[$index]);
                        if (!empty($name) && !in_array($name, $participants)) {
                            $participants[] = $name;
                            debug_log("Found player from CSV: " . $name);
                        }
                    }
                }
            }
        }
        fclose($handle);
    }
}

debug_log("Players from CSV: " . implode(', ', $participants));

// キャッシュファイルからプレイヤーリストを補完
$players_cache = __DIR__ . '/data/cache_players.json';
if (file_exists($players_cache)) {
    $cache_content = file_get_contents($players_cache);
    debug_log("Cache content: " . $cache_content);
    
    $cached_players = json_decode($cache_content, true);
    debug_log("Decoded cache: " . ($cached_players ? implode(', ', $cached_players) : 'decode failed'));
    
    if (is_array($cached_players)) {
        foreach ($cached_players as $player) {
            if (!empty($player) && !in_array($player, $participants)) {
                $participants[] = $player;
                debug_log("Added player from cache: " . $player);
            }
        }
    }
}

// 名前を五十音順にソート（UTF-8対応）
if (!empty($participants)) {
    debug_log("Before sorting: " . implode(', ', $participants));
    if (class_exists('Collator')) {
        $collator = new Collator('ja_JP');
        $collator->sort($participants);
    } else {
        sort($participants);
    }
    debug_log("After sorting: " . implode(', ', $participants));
} else {
    $participants = ['プレイヤーデータがありません'];
    debug_log("No players found");
}

debug_log("Final player list: " . implode(', ', $participants));

include 'includes/header.php';
?>
        <h1>成績入力</h1>
        <form action="save_score.php" method="post">
            
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="player-input">
                <label for="name<?php echo $i; ?>">参加者<?php echo $i + 1; ?>:</label>
                <select id="name<?php echo $i; ?>" name="players[<?php echo $i; ?>][name]" required>
                    <option value="" disabled selected>名前を選択</option>
                    <?php foreach ($participants as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="players[<?php echo $i; ?>][score]" class="score-input" placeholder="点数 (例: 364)" required>
                <span>00</span>
            </div>
            <?php endfor; ?>

            <div class="score-summary">
                <div class="score-total">
                    合計: <span id="total-score">0</span>00
                </div>
                <div id="score-error" class="error-message" style="display: none;">
                    合計が100,000点 (入力値1000) になっていません。
                </div>
            </div>

            <input type="submit" id="submit-button" value="この内容で記録する" class="submit-btn" disabled>
        </form>
<?php include 'includes/footer.php'; ?>