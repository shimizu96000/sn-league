<?php
require_once 'includes/init.php';
date_default_timezone_set('Asia/Tokyo');

$cache_file = __DIR__ . '/data/cache_matches.csv';
$matches = [];
// CSV source URL (same as match_plans.php) - used when ?refresh=1 is requested
$csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSQhT-tkB_Eiiq20zBoNa0nBfuexmir6mRj0A7UzLbjbJncnRWZUk4CtRBSMg03ALYdZ6n1kaUalPtj/pub?gid=0&single=true&output=csv';

function curl_get_contents_admin($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// If refresh requested, fetch remote CSV and overwrite cache file safely
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    $remote = curl_get_contents_admin($csv_url);
    if ($remote !== false && strpos($remote, '日時') !== false) {
        $tmp = tempnam(sys_get_temp_dir(), 'matches');
        file_put_contents($tmp, $remote);
        rename($tmp, $cache_file);
        // reload the page without refresh param to show updated list
        header('Location: admin_seats.php?refreshed=1');
        exit;
    } else {
        // continue and show existing cache if fetch failed
        $fetch_error = true;
    }
}
if (file_exists($cache_file)) {
    $csv = file_get_contents($cache_file);
    $csv = mb_convert_encoding($csv, 'UTF-8', 'auto');
    $lines = explode("\n", $csv);
    foreach ($lines as $i => $line) {
        if ($i === 0 || trim($line) === '') continue;
        $row = str_getcsv($line);
        if (empty($row[0])) continue;
        $date_str = $row[0];
        // parse date to day key
        $dt = DateTime::createFromFormat('Y/n/j G:i~', $date_str, new DateTimeZone('Asia/Tokyo'));
        if ($dt === false) continue;
        $day_key = $dt->format('Y-n-j');
        $time = $dt->format('H:i');
        $players = array_filter([trim($row[1] ?? ''), trim($row[2] ?? ''), trim($row[3] ?? ''), trim($row[4] ?? '')]);
        if (count($players) === 0) continue;
        $matches[] = [
            'day_key' => $day_key,
            'datetime' => $dt->format(DateTime::ATOM),
            'time' => $time,
            'raw_date' => $date_str,
            'players' => $players,
        ];
    }
}

// sort matches by datetime descending so latest dates appear first
usort($matches, function($a, $b) {
    return strcmp($b['datetime'], $a['datetime']);
});

$seats_file = __DIR__ . '/data/combination_seats.json';
$combination_seats = [];
if (file_exists($seats_file)) {
    $json = file_get_contents($seats_file);
    $combination_seats = json_decode($json, true) ?: [];
}

include 'includes/header.php';
?>
<?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
    <div class="status-message success">席順を保存しました。</div>
<?php endif; ?>
<?php if (isset($_GET['refreshed']) && $_GET['refreshed'] == '1'): ?>
    <div class="status-message success">試合データを最新に更新しました。</div>
<?php endif; ?>
<?php if (!empty($fetch_error)): ?>
    <div class="status-message error">外部CSVの取得に失敗しました。既存のキャッシュを表示します。</div>
<?php endif; ?>
<h2>管理：試合計画の席順編集</h2>
<p>一覧から各試合の選手に座席番号を割り当て、保存してください。</p>
<?php if (empty($matches)): ?>
    <p>試合データが見つかりません。`cache_matches.csv` を確認してください。</p>
<?php else: ?>
    <form method="post" action="admin_save_seats.php">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
            <a href="admin.php" class="btn">管理画面に戻る</a>
            <button type="submit" class="btn">保存</button>
            <a href="admin_seats.php?refresh=1" class="btn">最新を取得</a>
        </div>
        <?php foreach ($matches as $idx => $m): ?>
            <?php $key = $m['day_key'] . '-' . $m['time'] . '-' . $idx; ?>
            <div class="match-block" style="border:1px solid #ddd;padding:10px;margin:10px 0;border-radius:6px;">
                <div><strong>日付:</strong> <?php echo htmlspecialchars($m['day_key'] . ' ' . $m['time']); ?></div>
                <div style="margin-top:6px;">
                    <?php
                        // prepare seats for this match: preserve existing assignments and randomly fill missing ones
                        $playersForMatch = $m['players'];
                        // build combination key (sorted participants) to look up persistent seats
                        $sorted = $playersForMatch;
                        $sorted_for_key = $sorted;
                        sort($sorted_for_key);
                        $combo_key = implode('|', $sorted_for_key);
                        $assigned = [];
                        $seats = ['東','南','西','北'];
                        if (!empty($combination_seats[$combo_key])) {
                            // existing persistent mapping: seat => player
                            $assign_map = $combination_seats[$combo_key];
                            // normalize values
                            $assign_map_norm = [];
                            foreach ($assign_map as $seat_label => $player_name) {
                                $pname = trim((string)$player_name);
                                if ($pname !== '') $assign_map_norm[$seat_label] = $pname;
                            }
                            // check how many mapped players actually belong to this match
                            $mapped_players = array_values($assign_map_norm);
                            $common = array_intersect($mapped_players, $playersForMatch);
                            // require full match (all players) to trust the persistent mapping; otherwise fall back
                            if (count($common) === count($playersForMatch)) {
                                // reverse map: player => seat
                                foreach ($assign_map_norm as $seat_label => $player_name) {
                                    $assigned[$player_name] = $seat_label;
                                }
                            } else {
                                // fall back to generated mapping below
                                $assign_map = null;
                            }
                        }
                        if (empty($assigned)) {
                            // no persistent mapping: generate default the same way as match_plans
                            // use the raw date string if available to seed shuffle
                            $seed = $m['raw_date'] ?? ($m['datetime'] ?? implode(' ', [$m['day_key'], $m['time']]));
                            // seeded shuffle of participants
                            $shuffled = $playersForMatch;
                            // create a deterministic seed from the seed string
                            $seed_num = crc32($seed);
                            mt_srand($seed_num);
                            for ($i = count($shuffled)-1; $i > 0; $i--) {
                                $j = mt_rand(0, $i);
                                $tmp = $shuffled[$i]; $shuffled[$i] = $shuffled[$j]; $shuffled[$j] = $tmp;
                            }
                            foreach ($shuffled as $idx2 => $pname) {
                                if (isset($seats[$idx2])) $assigned[$pname] = $seats[$idx2];
                            }
                        }
                        // debug helper: show mapping info when requested
                        $show_debug = isset($_GET['debug']) && $_GET['debug'] == '1';
                        if ($show_debug) {
                            echo '<div style="background:#fff8c6;padding:6px;margin:6px 0;border:1px dashed #e0c000;">';
                            echo '<strong>DEBUG:</strong><br>';
                            echo 'playersForMatch: ' . htmlspecialchars(implode(', ', $playersForMatch)) . '<br>';
                            echo 'combo_key: ' . htmlspecialchars($combo_key) . '<br>';
                            $has_entry = (isset($assign_map_norm) && count($assign_map_norm));
                            echo 'persistent entry exists: ' . ($has_entry ? 'yes' : 'no') . '<br>';
                            echo 'assign_map_norm: ' . htmlspecialchars(json_encode($assign_map_norm ?? [], JSON_UNESCAPED_UNICODE)) . '<br>';
                            echo 'assigned (final): ' . htmlspecialchars(json_encode($assigned, JSON_UNESCAPED_UNICODE)) . '<br>';
                            echo '</div>';
                        }
                    ?>
                    <?php foreach ($m['players'] as $p): ?>
                                    <?php
                                        $current_seat = $assigned[$p] ?? '';
                                    ?>
                        <div style="display:flex;align-items:center;gap:8px;margin:6px 0;">
                            <div style="width:160px;"><?php echo htmlspecialchars($p); ?></div>
                            <select name="seats[<?php echo $idx; ?>][<?php echo htmlspecialchars($p); ?>]" required>
                                <option value="">-- 席 --</option>
                                <option value="東" <?php if ($current_seat == '東') echo 'selected'; ?>>東</option>
                                <option value="南" <?php if ($current_seat == '南') echo 'selected'; ?>>南</option>
                                <option value="西" <?php if ($current_seat == '西') echo 'selected'; ?>>西</option>
                                <option value="北" <?php if ($current_seat == '北') echo 'selected'; ?>>北</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div style="margin-top:12px;">
            <button type="submit" class="btn">保存</button>
        </div>
    </form>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<?php
// EOF
