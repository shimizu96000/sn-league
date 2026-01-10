<?php
require_once 'includes/init.php';
$page_title = '試合計画一覧';
$current_page = basename(__FILE__);
date_default_timezone_set('Asia/Tokyo');
$csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSQhT-tkB_Eiiq20zBoNa0nBfuexmir6mRj0A7UzLbjbJncnRWZUk4CtRBSMg03ALYdZ6n1kaUalPtj/pub?gid=0&single=true&output=csv';

function curl_get_contents($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function seeded_shuffle(array &$array, string $seed) {
    // stable, deterministic shuffle based on hash of seed+value
    // This ensures the same participants can produce different orders if seed differs (e.g., different match id)
    $hashes = [];
    foreach ($array as $k => $v) {
        $h = sha1($seed . '|' . $v);
        $hashes[$k] = hexdec(substr($h, 0, 12));
    }
    asort($hashes, SORT_NUMERIC);
    $new = [];
    foreach ($hashes as $k => $_) $new[] = $array[$k];
    $array = $new;
}

// non-deterministic secure shuffle using random_int
function random_shuffle_secure(array &$array) {
    $n = count($array);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $array[$i];
        $array[$i] = $array[$j];
        $array[$j] = $tmp;
    }
}

// Read past orders for the same 4-player sorted key from scores.csv
function get_past_orders_for_combo(array $sorted_names) {
    $file = __DIR__ . '/data/scores.csv';
    $orders = [];
    if (!file_exists($file)) return $orders;
    if (($fp = fopen($file, 'r')) === false) return $orders;
    while (($row = fgetcsv($fp)) !== false) {
        if (count($row) < 11) continue;
        $names = [$row[1] ?? '', $row[4] ?? '', $row[7] ?? '', $row[10] ?? ''];
        $names_sorted = $names;
        sort($names_sorted);
        if ($names_sorted === $sorted_names) {
            // record the order as array
            $orders[] = $names;
        }
    }
    fclose($fp);
    return $orders;
}

// Choose a seat order for participants that satisfies constraints
function choose_nonduplicating_order(array $participants, array $sorted_names) {
    // collect past orders
    $past_orders = get_past_orders_for_combo($sorted_names);
    $past_count = count($past_orders);
    $immediate_last = $past_count > 0 ? $past_orders[$past_count - 1] : null;

    // if past_count >= 24, duplicates allowed except must not equal immediate_last
    $allow_dup = ($past_count >= 24);

    // try random attempts
    $attempts = 0;
    $max_attempts = 200;
    while ($attempts++ < $max_attempts) {
        $cand = $participants;
        // secure random shuffle
        $n = count($cand);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $cand[$i]; $cand[$i] = $cand[$j]; $cand[$j] = $tmp;
        }
        // check immediate last
        if ($immediate_last !== null && $cand === $immediate_last) continue;
        if ($allow_dup) return $cand;
        // ensure cand is not equal to any past order
        $found = false;
        foreach ($past_orders as $po) { if ($po === $cand) { $found = true; break; } }
        if (!$found) return $cand;
    }

    // fallback: try enumerating permutations to find a valid one
    $perms = permutations($participants);
    foreach ($perms as $p) {
        if ($immediate_last !== null && $p === $immediate_last) continue;
        if ($allow_dup) return $p;
        $dup = false; foreach ($past_orders as $po) { if ($po === $p) { $dup = true; break; } }
        if (!$dup) return $p;
    }

    // if still none, as last resort return random but not equal to immediate_last if possible
    if ($immediate_last !== null) {
        foreach ($perms as $p) { if ($p !== $immediate_last) return $p; }
    }
    return $participants;
}

// helper: generate permutations (simple recursive)
function permutations(array $items) {
    if (count($items) <= 1) return [$items];
    $result = [];
    foreach ($items as $k => $v) {
        $rest = $items;
        array_splice($rest, $k, 1);
        foreach (permutations($rest) as $perm) {
            array_unshift($perm, $v);
            $result[] = $perm;
        }
    }
    return $result;
}

// prefer local cache if present; allow manual refresh via ?refresh=1
$cache_file = __DIR__ . '/data/cache_matches.csv';
$all_match_plans = [];
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    $remote = curl_get_contents($csv_url);
    if ($remote !== false && strpos($remote, '日時') !== false) {
        $tmp = tempnam(sys_get_temp_dir(), 'matches');
        file_put_contents($tmp, $remote);
        rename($tmp, $cache_file);
        header('Location: match_plans.php?refreshed=1'); exit;
    } else {
        $fetch_error = true;
        $csv_data = false;
    }
}

if (file_exists($cache_file)) {
    $csv_data = file_get_contents($cache_file);
} else {
    $csv_data = curl_get_contents($csv_url);
}

if ($csv_data !== false && strpos($csv_data, '日時') !== false) {
    $csv_data = mb_convert_encoding($csv_data, 'UTF-8', 'auto');
    $lines = explode("\n", $csv_data);
    
    foreach ($lines as $index => $line) {
        if ($index == 0 || trim($line) === '') continue;
        $data_row = str_getcsv($line);
        if (!empty($data_row[0])) {
            $date = htmlspecialchars($data_row[0] ?? '');
            // keep both raw and escaped participant lists
            $participants_raw = array_values(array_filter([
                trim($data_row[1] ?? ''),
                trim($data_row[2] ?? ''),
                trim($data_row[3] ?? ''),
                trim($data_row[4] ?? '')
            ]));
            $participants_escaped = array_map('htmlspecialchars', $participants_raw);
            $all_match_plans[] = ['date' => $date, 'participants' => $participants_escaped, 'raw_participants' => $participants_raw, 'seed' => $index];
        }
    }
}
$all_match_plans = array_reverse($all_match_plans);
$current_datetime_php = date('Y年m月d日 H:i:s');
// load persistent combination seats if exists
$seat_file = __DIR__ . '/data/combination_seats.json';
$combination_seats = [];
if (file_exists($seat_file)) {
    $combination_seats = json_decode(file_get_contents($seat_file), true) ?? [];
}

include 'includes/header.php';
?>
        <h1>試合計画一覧</h1>
    <?php if (isset($_GET['refreshed']) && $_GET['refreshed'] == '1'): ?><div class="status-message success">試合データを最新に更新しました。</div><?php endif; ?>
    <?php if (!empty($fetch_error)): ?><div class="status-message error">外部CSVの取得に失敗しました。キャッシュを表示しています。</div><?php endif; ?>
        <p class="current-datetime">現在日時: <span id="current-time"><?php echo $current_datetime_php; ?></span></p>
    <div style="text-align:center;margin-bottom:12px;"><a href="match_plans.php?refresh=1" class="btn">最新を取得</a></div>

        <div class="section">
            <h2>これまでの試合計画</h2>
            <div class="table-container">
                <?php if (empty($all_match_plans)): ?>
                    <p style="text-align: center; margin-top: 20px;">まだ試合計画が登録されていません。</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>日時</th>
                                <th>座席</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_match_plans as $plan): ?>
                                <tr>
                                    <td><?php echo $plan['date']; ?></td>
                                    <td>
                                        <div class="seat-assignments-table">
                                        <?php
                                        if (!empty($plan['participants'])) {
                                                            $participants_escaped = $plan['participants'];
                                                            $participants_raw = $plan['raw_participants'] ?? array_map('htmlspecialchars_decode', $participants_escaped);
                                                            $sorted = $participants_raw;
                                                            sort($sorted);
                                                            $key = implode('|', $sorted);
                                                            // 既に永続化された席順があれば使用
                                                            if (!empty($combination_seats[$key])) {
                                                                $assign = $combination_seats[$key];
                                                                $seats = ['東','南','西','北'];
                                                                foreach ($seats as $s) {
                                                                    echo '<div class="seat-item"><span class="seat-wind">' . $s . '</span>: ' . htmlspecialchars($assign[$s] ?? '') . '</div>';
                                                                }
                                                            } else {
                                                                // no persistent mapping: choose a non-duplicating order based on past records using unescaped names
                                                                $tmp_sorted = $sorted; sort($tmp_sorted);
                                                                $chosen = choose_nonduplicating_order($participants_raw, $tmp_sorted);
                                                                $seats = ['東', '南', '西', '北'];
                                                                foreach ($chosen as $index => $player) {
                                                                    if (isset($seats[$index])) {
                                                                        echo '<div class="seat-item"><span class="seat-wind">' . $seats[$index] . '</span>: ' . htmlspecialchars($player) . '</div>';
                                                                    }
                                                                }
                                                            }
                                        } else {
                                            echo '<div class="seat-item">参加者: 未定</div>';
                                        }
                                        ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
<?php include 'includes/footer.php'; ?>