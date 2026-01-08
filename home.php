<?php
require_once 'includes/init.php';
$page_title = 'ホーム';
$current_page = basename(__FILE__);

$csv_url = "https://docs.google.com/spreadsheets/d/1E0RvLCAXcMj6L0UwmAWKmHJ-zXZQDHPCpx0L-rPyL6o/export?format=csv&gid=0";

function curl_get_contents($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('curl error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return $result;
}

function get_past_orders_for_combo_home(array $sorted_names) {
    $fp = @fopen(__DIR__ . '/data/scores.csv', 'r');
    if (!$fp) return [];
    $orders = [];
    while ($row = fgetcsv($fp)) {
        $names = [$row[1] ?? '', $row[4] ?? '', $row[7] ?? '', $row[10] ?? ''];
        $names_sorted = $names; sort($names_sorted);
        if ($names_sorted === $sorted_names) $orders[] = $names;
    }
    fclose($fp);
    return $orders;
}

function permutations_home(array $items) {
    if (count($items) <= 1) return [$items];
    $result = [];
    foreach ($items as $k => $v) {
        $rest = $items; array_splice($rest, $k, 1);
        foreach (permutations_home($rest) as $perm) { array_unshift($perm, $v); $result[] = $perm; }
    }
    return $result;
}

function choose_nonduplicating_order_home(array $participants, array $sorted_names) {
    $past_orders = get_past_orders_for_combo_home($sorted_names);
    $past_count = count($past_orders);
    $immediate_last = $past_count > 0 ? $past_orders[$past_count - 1] : null;
    $allow_dup = ($past_count >= 24);
    $attempts = 0; $max_attempts = 200;
    while ($attempts++ < $max_attempts) {
        $cand = $participants;
        $n = count($cand);
        for ($i = $n - 1; $i > 0; $i--) { $j = random_int(0, $i); $tmp = $cand[$i]; $cand[$i] = $cand[$j]; $cand[$j] = $tmp; }
        if ($immediate_last !== null && $cand === $immediate_last) continue;
        if ($allow_dup) return $cand;
        $found = false; foreach ($past_orders as $po) { if ($po === $cand) { $found = true; break; } }
        if (!$found) return $cand;
    }
    $perms = permutations_home($participants);
    foreach ($perms as $p) {
        if ($immediate_last !== null && $p === $immediate_last) continue;
        if ($allow_dup) return $p;
        $dup = false; foreach ($past_orders as $po) { if ($po === $p) { $dup = true; break; } }
        if (!$dup) return $p;
    }
    if ($immediate_last !== null) { foreach ($perms as $p) { if ($p !== $immediate_last) return $p; } }
    return $participants;
}

$match_plan = ['date' => '次の試合は未定です', 'participants' => []];
$csv_data = curl_get_contents($csv_url);
$now_timestamp = time();
$next_match_timestamp = PHP_INT_MAX;

if ($csv_data !== false && strpos($csv_data, '日時') !== false) {
    $csv_data = mb_convert_encoding($csv_data, 'UTF-8', 'auto');
    $lines = explode("\n", $csv_data);

    foreach($lines as $index => $line) {
        if ($index == 0 || trim($line) === '') continue;
        $data_row = str_getcsv($line);
        if (!empty($data_row[0])) {
            $date_str = $data_row[0];
            $dt_object = DateTime::createFromFormat('Y/n/j G:i~', $date_str, new DateTimeZone('Asia/Tokyo'));
            if ($dt_object !== false) {
                $match_timestamp = $dt_object->getTimestamp();
                if ($match_timestamp > $now_timestamp && $match_timestamp < $next_match_timestamp) {
                    $next_match_timestamp = $match_timestamp;
                    $participants_raw = array_values(array_filter([
                        trim($data_row[1] ?? ''),
                        trim($data_row[2] ?? ''),
                        trim($data_row[3] ?? ''),
                        trim($data_row[4] ?? '')
                    ]));
                    $participants_array = array_map('htmlspecialchars', $participants_raw);
                    $match_plan['date'] = htmlspecialchars($date_str);
                    $match_plan['participants'] = $participants_array;
                    $match_plan['raw_participants'] = $participants_raw;
                    $match_plan['id'] = $index;
                }
            }
        }
    }
} else {
    $match_plan['date'] = '試合計画の取得に失敗しました。';
}

$current_datetime_php = date('Y年m月d日 H:i:s');
$about_snl_content = '説明文の読み込みに失敗しました。';
$about_snl_file = __DIR__ . '/data/about_snl.txt';
if (file_exists($about_snl_file)) {
    $about_snl_content_raw = file_get_contents($about_snl_file);
    $paragraphs = preg_split('/(\r\n|\n|\r){2,}/', $about_snl_content_raw);
    $about_snl_content = '';
    foreach ($paragraphs as $p) {
        if (trim($p) !== '') {
            $about_snl_content .= '<p>' . htmlspecialchars($p) . '</p>';
        }
    }
}

// お知らせの読み込み
$announcements_file = __DIR__ . '/data/announcements.json';
$announcements = [];
if (file_exists($announcements_file)) {
    $data = json_decode(file_get_contents($announcements_file), true) ?? [];
    $announcements = $data['announcements'] ?? [];
    
    usort($announcements, function($a, $b) {
        return strtotime($b['updated_at'] ?? $b['created_at']) - strtotime($a['updated_at'] ?? $a['created_at']);
    });
    
    $announcements = array_slice($announcements, 0, 5);
}

// TIPSの読み込み
$tips = [];
$tips_file = __DIR__ . '/data/tips_data.json';
if (file_exists($tips_file)) {
    $tips_data = json_decode(file_get_contents($tips_file), true) ?? [];
    // tips_data.json は配列形式なので直接使用
    $tips = is_array($tips_data) ? $tips_data : [];
}

// 承認済みTIPSのみをフィルタリング
$approved_tips = array_filter($tips, function($tip) {
    return isset($tip['status']) && $tip['status'] === 'approved';
});

// TIPSがある場合、ランダムに表示用データを準備
$tips_json = json_encode(array_values($approved_tips), JSON_HEX_APOS | JSON_HEX_QUOT);

include 'includes/header.php';
?>

        <!-- ヒーロー -->
        <div class="home-hero">
            <h2>SN.LEAGUE へようこそ</h2>
            <p>麻雀リーグの成績・対戦成績を一元管理</p>
        </div>

        <!-- TIPSセクション -->
        <?php if (!empty($approved_tips)): ?>
        <div class="home-tips">
            <h2>TIPS</h2>
            <div id="tips-content" class="tips-text"></div>
        </div>

        <script>
        const tipsData = <?php echo $tips_json; ?>;
        let currentTipIndex = -1;

        function showRandomTip() {
            if (tipsData.length === 0) return;
            
            let newIndex;
            do {
                newIndex = Math.floor(Math.random() * tipsData.length);
            } while (newIndex === currentTipIndex && tipsData.length > 1);
            
            currentTipIndex = newIndex;
            const tipElement = document.getElementById('tips-content');
            tipElement.textContent = tipsData[currentTipIndex].content;
        }

        showRandomTip();
        setInterval(showRandomTip, 30000);
        </script>
        <?php endif; ?>

        <!-- お知らせセクション -->
        <?php if (!empty($announcements)): ?>
        <div class="home-section">
            <h2>お知らせ</h2>
            <div>
                <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-item">
                    <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                    <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    <small>📅 <?php echo htmlspecialchars(substr($announcement['updated_at'] ?? $announcement['created_at'], 0, 10)); ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SN.LEAGUEについて -->
        <div class="home-section">
            <h2>SNリーグについて</h2>
            <div class="about-box">
                <?php echo $about_snl_content; ?>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>
