<?php
// 管理画面（メンテナンスとメニュー公開設定）
require_once 'includes/init.php';

// 管理者権限が無ければログインへ
check_permission('manage_system');

$page_title = '管理画面';
$current_page = basename(__FILE__);

$menu_file = __DIR__ . '/data/menu_visibility.json';
$default_menus = [
    'home' => true,
    'score_form' => true,
    'view_scores' => true,
    'match_plans' => true,
    'calendar' => true,
    'rules' => true
];

$maintenance_file = __DIR__ . '/data/maintenance_status.txt';
$is_maintenance = file_exists($maintenance_file) && file_get_contents($maintenance_file) === '1';

$saved = false;

// POST 処理: メンテナンス切替、メニュー設定の保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // メンテナンストグル
    if (isset($_POST['mode'])) {
        if ($_POST['mode'] === 'maintenance_on') {
            file_put_contents($maintenance_file, '1');
            $is_maintenance = true;
        } elseif ($_POST['mode'] === 'maintenance_off') {
            file_put_contents($maintenance_file, '0');
            $is_maintenance = false;
        }
    }

    // メニュー設定保存
    if (isset($_POST['menus']) && is_array($_POST['menus'])) {
        $input = $_POST['menus'];
        $out = [];
        foreach ($default_menus as $key => $v) {
            $out[$key] = isset($input[$key]) && $input[$key] === '1' ? true : false;
        }
        file_put_contents($menu_file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $saved = true;
    }
}

// メニュー読み込み（無ければデフォルト）
$menus = $default_menus;
if (file_exists($menu_file)) {
    $json = file_get_contents($menu_file);
    $menus = array_merge($menus, json_decode($json, true) ?? []);
}

include 'includes/header.php';
?>
    <h1>管理画面</h1>
    <div class="admin-panel">
        <?php if ($saved): ?><p class="status-public">設定を保存しました。</p><?php endif; ?>
        <div class="section">
            <h2>メンテナンスモード</h2>
            <p>現在の状態: <strong class="<?php echo $is_maintenance ? 'status-maintenance' : 'status-public'; ?>"><?php echo $is_maintenance ? 'メンテナンス中' : '公開中'; ?></strong></p>
            <form action="admin.php" method="post" class="admin-form" style="display:inline-block; margin-right:12px;">
                <?php if ($is_maintenance): ?>
                    <button type="submit" name="mode" value="maintenance_off" class="btn">公開中にする</button>
                <?php else: ?>
                    <button type="submit" name="mode" value="maintenance_on" class="btn btn-external">メンテナンス中にする</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="section" style="margin-top:18px;">
            <h2>未対戦組合せ</h2>
            <p><a href="unplayed_combinations.php" class="btn">未対戦組合せを表示</a></p>
            <p style="margin-top:8px;"><a href="admin_seats.php" class="btn">試合の席順を編集</a></p>
        </div>

        <form method="POST" class="admin-form" style="margin-top:18px;">
            <h2>メニュー公開設定</h2>
            <p>各メニューを公開(表示) / 非公開(非表示) に切り替えます。</p>
            <table style="margin:0 auto;">
                <?php foreach ($menus as $key => $visible): ?>
                <tr>
                    <td style="padding-right:12px; text-align:left;"><?php echo htmlspecialchars($key); ?></td>
                    <td>
                        <label><input type="radio" name="menus[<?php echo $key; ?>]" value="1" <?php if ($visible) echo 'checked'; ?>> 公開</label>
                        <label style="margin-left:8px;"><input type="radio" name="menus[<?php echo $key; ?>]" value="0" <?php if (!$visible) echo 'checked'; ?>> 非公開</label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <div style="margin-top:12px;">
                <button type="submit" class="btn">保存</button>
            </div>
        </form>

        <div class="section" style="margin-top:18px;">
            <h2>コンテンツ管理</h2>
            <div class="admin-links">
                <p><a href="admin_quiz.php" class="btn">麻雀クイズの管理</a></p>
                <p><a href="admin_tips.php" class="btn">TIPSの管理</a></p>
                <p><a href="admin_announcements.php" class="btn">お知らせの管理</a></p>
                <p><a href="admin_titles.php" class="btn">称号の管理</a></p>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
