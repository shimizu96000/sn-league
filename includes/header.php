<?php
// PHPのエラー表示を抑制（本番環境向け）
// error_reporting(0);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) ?? 'SN LEAGUE'; ?> - SN LEAGUE</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="bitnami.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3/dist/chart.min.js"></script>
</head>
<body>
    <div class="container">
        <header class="main-header">
            <div class="main-title-logo-flex">
                <img src="assets/img/snl_logo.png" alt="SNリーグ コンパクトロゴ" class="compact-logo">
                <h1>SN <span class="league-text">LEAGUE</span></h1>
            </div>
            <p class="subtitle">Sasuga Naoki League</p>
        </header>

        <nav class="global-nav">
<?php
// menu_visibility.json からメニューごとの公開設定を読み込む（無ければすべて公開）
$menu_visibility = [];
$menu_file = __DIR__ . '/../data/menu_visibility.json';
if (file_exists($menu_file)) {
    $json = file_get_contents($menu_file);
    $menu_visibility = json_decode($json, true) ?? [];
}
// ユーティリティ：表示判定
function menu_visible($key, $menu_visibility) {
    if (!isset($menu_visibility[$key])) return true;
    return (bool)$menu_visibility[$key];
}
?>
            <?php if (menu_visible('home', $menu_visibility)): ?><a href="home.php" class="nav-item <?php if ($current_page === 'home.php') echo 'active'; ?>">ホーム</a><?php endif; ?>
            <div class="nav-item has-dropdown <?php if (in_array($current_page, ['score_form.php', 'view_scores.php', 'players_list.php'])) echo 'active'; ?>">
                成績
                <div class="dropdown-menu">
                    <?php if (menu_visible('score_form', $menu_visibility)): ?>
                        <a href="score_form.php" class="dropdown-item <?php if ($current_page === 'score_form.php') echo 'active'; ?>">成績入力</a>
                    <?php endif; ?>
                    <?php if (menu_visible('view_scores', $menu_visibility)): ?>
                        <a href="view_scores.php" class="dropdown-item <?php if ($current_page === 'view_scores.php') echo 'active'; ?>">成績閲覧</a>
                    <?php endif; ?>
                    <a href="players_list.php" class="dropdown-item <?php if ($current_page === 'players_list.php') echo 'active'; ?>">選手一覧</a>
                </div>
            </div>
            <?php if (menu_visible('match_plans', $menu_visibility)): ?><a href="match_plans.php" class="nav-item <?php if ($current_page === 'match_plans.php') echo 'active'; ?>">試合計画</a><?php endif; ?>
            <?php if (menu_visible('calendar', $menu_visibility)): ?><a href="calendar.php" class="nav-item <?php if ($current_page === 'calendar.php') echo 'active'; ?>">カレンダー</a><?php endif; ?>
            <?php if (menu_visible('rules', $menu_visibility)): ?>
            <div class="nav-item has-dropdown <?php if ($current_page === 'rules.php') echo 'active'; ?>">
                ルール
                <div class="dropdown-menu">
                    <a href="rules.php?type=match" class="dropdown-item <?php if ($current_page === 'rules.php' && $_GET['type'] === 'match') echo 'active'; ?>">試合ルール</a>
                    <a href="rules.php?type=league" class="dropdown-item <?php if ($current_page === 'rules.php' && $_GET['type'] === 'league') echo 'active'; ?>">リーグルール</a>
                </div>
            </div>
            <?php endif; ?>
            <div class="nav-item has-dropdown <?php if (in_array($current_page, ['random_seats.php', 'mahjong_quiz.php', 'submit_tips.php', 'unplayed_combinations.php'])) echo 'active'; ?>">
                各種ツール
                <div class="dropdown-menu">
                    <a href="random_seats.php" class="dropdown-item <?php if ($current_page === 'random_seats.php') echo 'active'; ?>">席順生成</a>
                    <a href="mahjong_quiz.php" class="dropdown-item <?php if ($current_page === 'mahjong_quiz.php') echo 'active'; ?>">麻雀クイズ</a>
                    <a href="submit_tips.php" class="dropdown-item <?php if ($current_page === 'submit_tips.php') echo 'active'; ?>">TIPS投稿</a>
                    <a href="unplayed_combinations.php" class="dropdown-item <?php if ($current_page === 'unplayed_combinations.php') echo 'active'; ?>">未対戦の組み合わせ</a>
                </div>
            </div>
            <?php if (menu_visible('management', $menu_visibility)): ?>
                <a href="management.php" class="nav-item <?php if ($current_page === 'management.php') echo 'active'; ?>">運営・協賛</a>
            <?php endif; ?>
            <a href="#" onclick="return showCalcConfirmDialog();" class="nav-item">点数計算</a>
            
            <!-- ログイン情報 -->
            <div class="nav-item has-dropdown" style="margin-left:auto;">
                <?php 
                $username = get_username();
                $role = get_user_role();
                $role_labels = ['guest' => '観戦者', 'player' => '選手', 'admin' => '管理者'];
                $role_label = $role_labels[$role] ?? '不明';
                ?>
                <span style="font-size:0.9em;">📌 <?php echo htmlspecialchars($username); ?> (<?php echo $role_label; ?>)</span>
                <div class="dropdown-menu" style="right:0; left:auto;">
                    <a href="logout.php" class="dropdown-item">ログアウト</a>
                </div>
            </div>
        </nav>

        <main class="main-content">