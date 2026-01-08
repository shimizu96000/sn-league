<?php
require_once 'includes/init.php';
$page_title = 'ルール';
$current_page = basename(__FILE__);

// ルールタイプの選択（デフォルトは試合ルール）
$selected_type = isset($_GET['type']) ? $_GET['type'] : 'match';

// 試合ルールの読み込み
$match_rules_file = __DIR__ . '/data/rules.txt';
$match_rules_content = '';
if (file_exists($match_rules_file)) {
    $match_rules_content = file_get_contents($match_rules_file);
    $match_rules_content = nl2br(htmlspecialchars($match_rules_content));
} else {
    $match_rules_content = '試合ルールファイルが見つかりません。';
}

// リーグルールの読み込み
$league_rules_file = __DIR__ . '/data/league_rules.txt';
$league_rules_content = '';
if (file_exists($league_rules_file)) {
    $league_rules_content = file_get_contents($league_rules_file);
    $league_rules_content = nl2br(htmlspecialchars($league_rules_content));
} else {
    $league_rules_content = 'リーグルールファイルが見つかりません。';
}

// ページタイトルをルールタイプに応じて変更
$page_title = ($selected_type === 'league' ? 'リーグルール' : '試合ルール');

include 'includes/header.php';
?>
        <h1><?php echo $page_title; ?></h1>
        
        <div class="section">
            <div class="rules-nav">
                <a href="?type=match" class="btn-rules <?php echo $selected_type === 'match' ? 'active' : ''; ?>">試合ルール</a>
                <a href="?type=league" class="btn-rules <?php echo $selected_type === 'league' ? 'active' : ''; ?>">リーグルール</a>
            </div>
            <div class="rules-content">
                <?php
                if ($selected_type === 'league') {
                    echo $league_rules_content;
                } else {
                    echo $match_rules_content;
                }
                ?>
            </div>
        </div>
<?php include 'includes/footer.php'; ?>