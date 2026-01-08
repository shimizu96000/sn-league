<?php
require_once 'includes/init.php';
$page_title = 'カレンダー';
$current_page = basename(__FILE__);
date_default_timezone_set('Asia/Tokyo');
$app_script_url = 'https://script.google.com/macros/s/AKfycbyCFgtZziO3ziHlmTpF2a3MhaiHR4VLs0-IJ_5EmZPLYJTuR9lrExg9thVc--UUntaW/exec';
$csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSQhT-tkB_Eiiq20zBoNa0nBfuexmir6mRj0A7UzLbjbJncnRWZUk4CtRBSMg03ALYdZ6n1kaUalPtj/pub?gid=0&single=true&output=csv';

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$view = isset($_GET['view']) && $_GET['view'] === 'attendance' ? 'attendance' : 'plan';

$prev_month = $month - 1; $prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }
$calendar_title = "{$year}年 {$month}月";

function get_data_with_cache($url, $cache_file_name, $cache_duration = 300) {
    $cache_file = __DIR__ . '/' . $cache_file_name;
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
        return file_get_contents($cache_file);
    } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // タイムアウト設定（接続と全体）を追加してブロッキングを抑制
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data) {
            file_put_contents($cache_file, $data, LOCK_EX);
        }
        return $data;
    }
}

$participants = [];
$bypass_cache = isset($_GET['refresh']) && $_GET['refresh'] == '1';
$players_json = null;
if ($bypass_cache) {
    // curl で直接取得してキャッシュを更新（file_get_contents はブロックされる場合があるため）
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $app_script_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0');
    $players_json = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);
    if ($players_json !== false && $players_json !== '') {
        @file_put_contents(__DIR__ . '/data/cache_players.json', $players_json, LOCK_EX);
    } else {
        // fallback to cache if curl failed
        $players_json = get_data_with_cache($app_script_url, __DIR__ . '/data/cache_players.json', 3600);
    }
} else {
    $players_json = get_data_with_cache($app_script_url, __DIR__ . '/data/cache_players.json', 3600); // キャッシュ1時間
}
if ($players_json) {
    $names_array = json_decode($players_json, true);
    if (is_array($names_array)) {
        foreach ($names_array as $name_with_prefix) {
            $name = preg_replace('/^\d+\./', '', trim($name_with_prefix));
            if (!empty($name)) { $participants[] = $name; }
        }
        sort($participants);
    }
}

$match_plans_by_day = [];
$csv_data = null;
if ($bypass_cache) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $csv_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0');
    $csv_data = curl_exec($ch);
    $csv_err = curl_error($ch);
    curl_close($ch);
    if ($csv_data !== false && $csv_data !== '') {
        @file_put_contents(__DIR__ . '/data/cache_matches.csv', $csv_data, LOCK_EX);
    } else {
        $csv_data = get_data_with_cache($csv_url, __DIR__ . '/data/cache_matches.csv', 3600);
    }
} else {
    $csv_data = get_data_with_cache($csv_url, __DIR__ . '/data/cache_matches.csv', 3600); // キャッシュ1時間
}
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
                $day_key = $dt_object->format('Y-n-j');
                $plan_participants_array = array_filter([
                    htmlspecialchars($data_row[1] ?? ''), htmlspecialchars($data_row[2] ?? ''),
                    htmlspecialchars($data_row[3] ?? ''), htmlspecialchars($data_row[4] ?? '')
                ]);
                $match_plans_by_day[$day_key][] = $plan_participants_array;
            }
        }
    }
}

$attendance_data = [];
$attendance_json = get_data_with_cache($app_script_url . '?action=getAttendance', __DIR__ . '/data/cache_attendance.json', 600); // 出欠は短めに10分
if ($attendance_json) {
    $raw_attendance = json_decode($attendance_json, true);
    if(is_array($raw_attendance)) {
        foreach ($raw_attendance as $index => $row) {
            if ($index == 0 || !isset($row[0], $row[1], $row[2])) continue;
            $date_str = trim($row[0]);
            $player_name = $row[1];
            $status = $row[2];
            // 日付文字列を DateTime に変換して 'Y-n-j' 形式でキー化（外部フォーマット差異に対応）
            $date_key = null;
            if ($date_str !== '') {
                try {
                    // 受け取り文字列にタイムゾーン情報が含まれている可能性があるため、
                    // まずはそのまま DateTime に解析させ、JST に変換して日付キーを作る
                    $dt = new DateTime($date_str);
                    $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));
                    $date_key = $dt->format('Y-n-j');
                } catch (Exception $e) {
                    // fallback: strtotime 経由
                    $ts = strtotime($date_str);
                    if ($ts !== false) {
                        $date_key = date('Y-n-j', $ts);
                    }
                }
            }
            if ($date_key === null) continue;
            $attendance_data[$date_key][$player_name] = $status;
        }
    }
}

$calendar_weeks = [];
$first_day_of_month = new DateTime("{$year}-{$month}-01");
$last_day_of_month = new DateTime("{$year}-{$month}-" . $first_day_of_month->format('t'));
$day_of_week = (int)$first_day_of_month->format('w');
$day_counter = 1;
$week = [];
for ($i = 0; $i < $day_of_week; $i++) { $week[] = ''; }
while ($day_counter <= (int)$last_day_of_month->format('d')) {
    $week[] = $day_counter;
    if (count($week) == 7) { $calendar_weeks[] = $week; $week = []; }
    $day_counter++;
}
if (!empty($week)) {
    while (count($week) < 7) { $week[] = ''; }
    $calendar_weeks[] = $week;
}

include 'includes/header.php';
?>
        <div class="calendar-controls">
            <div class="calendar-nav">
                <a href="?year=<?php echo $prev_year; ?>&month=<?php echo $prev_month; ?>&view=<?php echo $view; ?>" class="btn-nav">‹ 前月</a>
                <span class="calendar-title"><?php echo $calendar_title; ?></span>
                <a href="?year=<?php echo $next_year; ?>&month=<?php echo $next_month; ?>&view=<?php echo $view; ?>" class="btn-nav">次月 ›</a>
            </div>
            <div class="view-toggle">
                <a href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>&view=plan" class="btn-view <?php if($view === 'plan') echo 'active'; ?>">試合計画</a>
                <a href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>&view=attendance" class="btn-view <?php if($view === 'attendance') echo 'active'; ?>">出欠状況</a>
            </div>
        </div>

        <div class="table-container">
            <table class="calendar-table">
                <thead>
                    <tr><th class="sun">日</th><th>月</th><th>火</th><th>水</th><th>木</th><th>金</th><th class="sat">土</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($calendar_weeks as $week): ?>
                        <tr>
                            <?php foreach ($week as $day): ?>
                                <?php
                                $cell_class = '';
                                if ($day && date('Y-n-j') == "{$year}-{$month}-{$day}") $cell_class .= ' today';
                                $day_key = $day ? "{$year}-{$month}-{$day}" : '';
                                if ($day && isset($match_plans_by_day[$day_key])) $cell_class .= ' has-match';
                                ?>
                                <td class="<?php echo $cell_class; ?>" data-date="<?php echo $day_key; ?>">
                                    <?php if ($day): ?>
                                        <div class="day-number"><?php echo $day; ?></div>
                                        <div class="day-content">
                                            <?php if ($view === 'plan' && isset($match_plans_by_day[$day_key])): ?>
                                                <div class="plan-list">
                                                <?php foreach ($match_plans_by_day[$day_key] as $match_participants): ?>
                                                    <div class="plan-item">
                                                    <?php foreach ($match_participants as $p_name): ?>
                                                        <div class="attendance-item status-参加" title="<?php echo htmlspecialchars($p_name); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($p_name, 0, 1)); ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                </div>
                                            <?php elseif ($view === 'attendance' && !empty($participants)): ?>
                                                <div class="attendance-list">
                                                <?php foreach ($participants as $p_name): ?>
                                                    <?php
                                                    $status = $attendance_data[$day_key][$p_name] ?? '未定';
                                                    if ($status === '未定') continue;
                                                    ?>
                                                    <div class="attendance-item status-<?php echo strtolower($status); ?>" title="<?php echo htmlspecialchars($p_name); ?>">
                                                        <?php echo htmlspecialchars(mb_substr($p_name, 0, 1)); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="action-buttons">
            <button id="open-modal-btn" class="btn">出欠を登録する</button>
        </div>
        <div id="selection-instruction" class="selection-instruction" style="display:none;">
            複数日選択モードです。日付をクリックして選択し、完了したらもう一度「出欠を登録する」を押して登録してください。キャンセルするには選択を解除するかモーダルを閉じてください。
        </div>
        </div>
<?php include 'includes/footer.php'; ?>
<!-- 出欠登録モーダル（オーバーレイなし、固定小窓） -->
<div id="attendance-modal" class="modal-content" style="display:none;">
    <span id="close-modal-btn" class="close-btn">&times;</span>
    <h2>出欠登録</h2>
    <form id="attendance-form" method="post" action="update_attendance.php">
        <!-- 複数日選択用（カンマ区切りの 'Y-n-j' 形式） -->
        <input type="hidden" name="dates" id="attendance-dates" value="">
        <div class="form-group">
            <label>選択日</label>
            <div id="selected-dates-display" style="min-height:28px; padding:6px 8px; border:1px solid #ddd; border-radius:4px; background:#fafafa;"></div>
        </div>
        <div class="form-group">
            <label for="player-select">選手</label>
            <select id="player-select" name="player" required>
                <?php foreach ($participants as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group status-options">
            <label><input type="radio" name="status" value="参加" checked> 参加</label>
            <label><input type="radio" name="status" value="不参加"> 不参加</label>
            <label><input type="radio" name="status" value="未定"> 未定</label>
        </div>
        <div style="text-align: right;">
            <button type="submit" class="btn">保存</button>
        </div>
    </form>
</div>