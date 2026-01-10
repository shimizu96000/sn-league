<?php
$page_title = '保存完了';
$current_page = 'score_form.php'; // メニューでは「成績入力」をアクティブにする
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php'; // init.phpを読み込む

$results = $_SESSION['last_recorded_results']['results'] ?? null;
$is_official = $_SESSION['last_recorded_results']['is_official'] ?? false;

unset($_SESSION['last_recorded_results']);

if (!$results) {
    header('Location: home.php');
    exit();
}

include 'includes/header.php';
?>
    <h1>成績を記録しました</h1>
    
    <div class="match-type-info">
        この試合は <strong><?php echo $is_official ? '公式戦' : '非公式戦'; ?></strong>として記録されました。
    </div>
    
    <div class="table-container">
        <table class="result-table">
            <tr><th>順位</th><th>名前</th><th>素点</th><th>最終スコア</th></tr>
            <?php foreach ($results as $player): ?>
                <tr>
                    <td class="rank-<?php echo $player['rank']; ?>"><?php echo htmlspecialchars($player['rank']); ?>位</td>
                    <td><?php echo htmlspecialchars($player['name']); ?></td>
                    <td><?php echo number_format($player['score']); ?>点</td>
                    <td class="<?php echo ($player['final_score'] >= 0) ? 'positive' : 'negative'; ?>"><?php echo sprintf('%+.1f', $player['final_score']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php include 'includes/footer.php'; ?>