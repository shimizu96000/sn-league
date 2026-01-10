<?php
require_once 'includes/init.php';
$page_title = 'ランダム席順生成';
$current_page = basename(__FILE__);

$selected_players = isset($_POST['players']) ? $_POST['players'] : [];
$generated_seats = [];

if (count($selected_players) === 4) {
    shuffle($selected_players);
    $winds = ['東', '南', '西', '北'];
    foreach ($selected_players as $i => $player) {
        $generated_seats[] = [
            'wind' => $winds[$i],
            'player' => $player
        ];
    }
}

// プレイヤーリストを取得
$players = [];
$score_file = __DIR__ . '/data/scores.csv';
if (file_exists($score_file)) {
    $fp = fopen($score_file, 'r');
    while ($line = fgetcsv($fp)) {
        for ($i = 0; $i < 4; $i++) {
            $name = $line[$i * 3 + 1];
            if (!in_array($name, $players)) {
                $players[] = $name;
            }
        }
    }
    fclose($fp);
    sort($players);
}

include 'includes/header.php';
?>

<h1>ランダム席順生成</h1>
<p class="catchphrase-small">「運を味方に」</p>

<div class="section">
    <h2>プレイヤー選択</h2>
    <form method="post" action="">
        <div class="player-selection">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="player-input">
                    <label>プレイヤー<?php echo $i + 1; ?>:</label>
                    <select name="players[]" required>
                        <option value="">選択してください</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?php echo htmlspecialchars($player); ?>"
                                <?php echo isset($selected_players[$i]) && $selected_players[$i] === $player ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endfor; ?>
        </div>
        <button type="submit" class="submit-btn">席順を生成</button>
    </form>
</div>

<?php if (!empty($generated_seats)): ?>
    <div class="section">
        <h2>生成された席順</h2>
        <div class="seat-assignments">
            <?php foreach ($generated_seats as $seat): ?>
                <div class="seat-item">
                    <span class="seat-wind"><?php echo $seat['wind']; ?></span>
                    <?php echo htmlspecialchars($seat['player']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>