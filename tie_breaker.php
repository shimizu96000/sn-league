<?php
$page_title = '同点順位決定';
$current_page = 'tie_breaker.php';
require_once 'includes/init.php';

$tie_breaker_data = $_SESSION['tie_breaker_data'] ?? null;

if (!$tie_breaker_data || !isset($tie_breaker_data['players'])) {
    header('Location: home.php');
    exit('エラー: 同点解決データが見つかりません。');
}

$players_raw = $tie_breaker_data['players'];

// 順位を割り振る
$players_with_ranks = [];
$rank = 1;
$prev_score = null;
foreach ($players_raw as $i => $player) {
    if ($prev_score !== null && $player['score'] < $prev_score) {
        $rank = $i + 1;
    }
    $player['rank'] = $rank;
    $players_with_ranks[] = $player;
    $prev_score = $player['score'];
}

// 順位でグループ化
$grouped_by_rank = [];
foreach ($players_with_ranks as $player) {
    $grouped_by_rank[$player['rank']][] = $player;
}

include 'includes/header.php';
?>
    <h1>同点順位決定</h1>
    <div class="tie-breaker-info">
        <p>同点者がいます。各プレイヤーの最終順位をドロップダウンから選択してください。</p>
        <p>※麻雀のルールに則り、<strong>起家（東家）に近いプレイヤーを上位</strong>にしてください。</p>
    </div>

    <form id="tieBreakerForm" action="process_tie_breaker.php" method="POST" class="tie-breaker-form">
        <?php foreach ($grouped_by_rank as $rank => $players_at_rank): ?>
            <div class="rank-group">
                <?php if (count($players_at_rank) > 1): // 同点グループ ?>
                    <p class="tie-score-header"><?php echo number_format($players_at_rank[0]['score'] * 100); ?>点 のプレイヤー（<?php echo count($players_at_rank); ?>名）</p>
                    <div class="tie-player-list">
                        <?php
                        // この同点グループで利用可能な順位リストを作成
                        $available_ranks = range($rank, $rank + count($players_at_rank) - 1);
                        ?>
                        <?php foreach ($players_at_rank as $player): ?>
                            <div class="tie-player-row">
                                <span class="player-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                <select name="ranks[<?php echo htmlspecialchars($player['name']); ?>]" class="rank-select" required>
                                    <option value="">順位を選択</option>
                                    <?php foreach ($available_ranks as $r): ?>
                                        <option value="<?php echo $r; ?>"><?php echo $r; ?>位</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: // 非同点プレイヤー ?>
                    <p class="non-tie-player"><?php echo $rank; ?>位: <?php echo htmlspecialchars($players_at_rank[0]['name']); ?> (<?php echo number_format($players_at_rank[0]['score'] * 100); ?>点)</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <button type="submit" class="submit-btn" style="margin-top: 30px;">この順位で決定する</button>
    </form>

    <a href="home.php" class="back-link">キャンセルしてホームに戻る</a>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('tieBreakerForm');
            const rankSelects = Array.from(form.querySelectorAll('.rank-select'));

            // 同じ順位が選択されたらアラートを出す
            form.addEventListener('submit', function(e) {
                const selectedRanks = rankSelects.map(select => select.value);
                const uniqueRanks = new Set(selectedRanks);

                if (selectedRanks.length !== uniqueRanks.size) {
                    e.preventDefault(); // 送信を中止
                    alert('エラー: 同じ順位が複数のプレイヤーに選択されています。');
                }
            });
        });
    </script>
<?php include 'includes/footer.php'; ?>