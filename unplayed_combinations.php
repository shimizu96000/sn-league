<?php
require_once 'includes/init.php';
$page_title = '未対戦の組み合わせ確認';
$current_page = basename(__FILE__);

// 参加者リストを取得（score_form.php と同じ AppsScript URL を使う）
$participants_csv_url = 'https://script.google.com/macros/s/AKfycbyCFgtZziO3ziHlmTpF2a3MhaiHR4VLs0-IJ_5EmZPLYJTuR9lrExg9thVc--UUntaW/exec';

function curl_get_contents($url) { 
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $output = curl_exec($ch); 
    $error = curl_error($ch);
    curl_close($ch); 
    if ($error) error_log('CURL Error: ' . $error);
    return $output; 
}

// キャッシュファイルから参加者を読む
$cache_file = __DIR__ . '/data/cache_players.json';
$participants = [];

// キャッシュファイルから取得を試みる
if (file_exists($cache_file)) {
    $cached_data = json_decode(file_get_contents($cache_file), true);
    if (is_array($cached_data) && !empty($cached_data)) {
        $participants = $cached_data;
        error_log('Loaded participants from cache: ' . count($participants));
    }
}

// キャッシュが無い、または参加者が少ない場合はAPIから取得
if (count($participants) < 8) {
    $json_data = curl_get_contents($participants_csv_url);
    if ($json_data) {
        $names_array = json_decode($json_data, true);
        if (is_array($names_array)) {
            $participants = [];
            foreach ($names_array as $name_with_prefix) { 
                $name = preg_replace('/^\d+\./', '', trim($name_with_prefix)); 
                if (!empty($name)) $participants[] = $name; 
            }
            // キャッシュに保存
            file_put_contents($cache_file, json_encode($participants, JSON_UNESCAPED_UNICODE));
            error_log('Fetched participants from API: ' . count($participants));
        }
    }
}

sort($participants);

// デバッグ情報を記録
error_log('Participants count: ' . count($participants));
error_log('Participants: ' . json_encode($participants));

// 必ず8人いる前提
if (count($participants) < 8) {
    echo '<div style="padding:20px; background:#fff5f5; border:1px solid #fc8181; border-radius:8px; margin:20px 0;">';
    echo '<h3 style="color:#c53030; margin-top:0;">エラー: 参加者が8人未満です</h3>';
    echo '<p>現在の参加者数: ' . count($participants) . '人</p>';
    echo '<p>登録されている参加者:</p>';
    echo '<ul>';
    foreach ($participants as $p) {
        echo '<li>' . htmlspecialchars($p) . '</li>';
    }
    echo '</ul>';
    echo '<p style="color:#666;">参加者リストを確認してください。</p>';
    echo '</div>';
    include 'includes/footer.php';
    exit;
}
$participants = array_slice($participants, 0, 8);

// 4人組合せを生成
function combinations($arr, $k) {
    $results = [];
    $n = count($arr);
    $indices = range(0, $k-1);
    while (true) {
        $combo = [];
        foreach ($indices as $i) $combo[] = $arr[$i];
        $results[] = $combo;
        $i = $k - 1;
        while ($i >= 0 && $indices[$i] == $i + $n - $k) $i--;
        if ($i < 0) break;
        $indices[$i]++;
        for ($j = $i + 1; $j < $k; $j++) $indices[$j] = $indices[$j-1] + 1;
    }
    return $results;
}

$all_combos = combinations($participants, 4);

// 公式戦で対戦済みの4人組合せを記録
$played_combos = [];
$save_file = __DIR__ . '/data/scores.csv';

if (file_exists($save_file)) {
    $fp = fopen($save_file, 'r');
    if ($fp !== false) {
        while (($row = fgetcsv($fp)) !== false) {
            // 最後のカラムをチェック（match_type）
            $last_col = count($row) - 1;
            if ($last_col < 0) continue;
            
            $match_type = trim($row[$last_col] ?? '');
            
            // 公式戦('official')のみを対象
            if ($match_type !== 'official') continue;
            
            // フォーマット: 日時, P1名, P1点, P1最終, P2名, P2点, P2最終, P3名, P3点, P3最終, P4名, P4点, P4最終, match_type
            // 名前は: index 1, 4, 7, 10
            $player_names = [
                isset($row[1]) ? trim($row[1]) : '',
                isset($row[4]) ? trim($row[4]) : '',
                isset($row[7]) ? trim($row[7]) : '',
                isset($row[10]) ? trim($row[10]) : ''
            ];
            
            // 空文字列を削除してリインデックス
            $player_names = array_values(array_filter($player_names, function($name) {
                return $name !== '';
            }));
            
            // ちょうど4人、かつ全員が参加者リストにいることを確認
            if (count($player_names) === 4) {
                $all_in_participants = true;
                foreach ($player_names as $name) {
                    if (!in_array($name, $participants)) {
                        $all_in_participants = false;
                        break;
                    }
                }
                
                if ($all_in_participants) {
                    sort($player_names);
                    $combo_key = implode('|', $player_names);
                    $played_combos[$combo_key] = true;
                }
            }
        }
        fclose($fp);
    }
}

// 永続化ファイル: 組合せ -> 席順(東,南,西,北)
$seat_file = __DIR__ . '/data/combination_seats.json';
$combination_seats = [];
if (file_exists($seat_file)) {
    $combination_seats = json_decode(file_get_contents($seat_file), true) ?? [];
}

// ステップ1: 全組合せから、公式戦で行われた組合せを除外して「未対戦」を抽出
$unplayed = [];
foreach ($all_combos as $combo) {
    // ソートしてキーを作成
    $sorted_names = $combo;
    sort($sorted_names);
    $combo_key = implode('|', $sorted_names);
    
    // この組合せが公式戦で対戦済みか確認
    if (isset($played_combos[$combo_key])) {
        // 対戦済み → スキップ
        continue;
    }
    
    // 未対戦 → 座席割り当てをして記録
    if (!isset($combination_seats[$combo_key])) {
        // 席順をランダムに決定（キーでシード固定）
        $seats = ['東', '南', '西', '北'];
        $seed = crc32($combo_key);
        mt_srand($seed);
        
        $shuffled_names = $sorted_names;
        for ($i = count($shuffled_names) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $shuffled_names[$i];
            $shuffled_names[$i] = $shuffled_names[$j];
            $shuffled_names[$j] = $tmp;
        }
        
        $assign = [];
        for ($i = 0; $i < 4; $i++) {
            $assign[$seats[$i]] = $shuffled_names[$i];
        }
        $combination_seats[$combo_key] = $assign;
    }
    
    $unplayed[$combo_key] = $combination_seats[$combo_key];
}

// キャッシュを保存
file_put_contents($seat_file, json_encode($combination_seats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ステップ2: 各選手の残り試合数を計算（未対戦組合せから）
$remaining_matches_count = array_fill_keys($participants, 0);
foreach ($unplayed as $combo_key => $assign) {
    $combo_players = array_values($assign);
    foreach ($combo_players as $player) {
        $remaining_matches_count[$player]++;
    }
}

// フィルタリング処理
$filtered_unplayed = $unplayed;
$selected_players = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST から選択された選手を取得
    for ($i = 1; $i <= 4; $i++) {
        $player_key = 'filter_player_' . $i;
        if (isset($_POST[$player_key]) && !empty($_POST[$player_key])) {
            $selected_players[] = $_POST[$player_key];
        }
    }
    
    // 重複を削除
    $selected_players = array_unique($selected_players);
    
    // 選択された選手がいる場合、フィルタを適用
    if (!empty($selected_players)) {
        $filtered_unplayed = [];
        
        foreach ($unplayed as $combo_key => $assign) {
            // この組合せに含まれる4人のプレイヤー
            $combo_players = array_values($assign);
            
            // 選択されたすべての選手がこの組合せに含まれているか確認
            $all_selected_included = true;
            foreach ($selected_players as $selected_player) {
                if (!in_array($selected_player, $combo_players)) {
                    $all_selected_included = false;
                    break;
                }
            }
            
            // 含まれている場合のみ、フィルタ結果に追加
            if ($all_selected_included) {
                $filtered_unplayed[$combo_key] = $assign;
            }
        }
    }
}

include 'includes/header.php';
?>
    <h1>未対戦の組み合わせ</h1>
    <p>公式戦としてまだ行われていない組み合わせを表示します。</p>
    <div style="background:#f0f7ff; border:1px solid #90cdf4; border-radius:8px; padding:12px; margin-bottom:20px;">
        <p style="margin:0;"><strong>参加者数: <?php echo count($participants); ?>人</strong></p>
    </div>

    <!-- 選手ごとの試合数 -->
    <div style="margin-bottom:30px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:15px;">
        <h2 style="margin-top:0; color:#374151;">各選手の残り試合数</h2>
        <p style="color:#666; margin-top:0; margin-bottom:15px;">未対戦の組み合わせに基づいた各選手の残り試合数</p>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;">
            <?php 
            // 各選手の最大試合数は C(7,3) = 35
            $max_remaining = 35;
            
            foreach ($participants as $player): ?>
                <?php 
                    $remaining = $remaining_matches_count[$player] ?? 0;
                    $progress_percent = ($remaining / $max_remaining) * 100;
                    
                    // 色を残り試合数によって変える
                    $color = '#10b981'; // 緑（少ない）
                    if ($remaining > ($max_remaining * 0.7)) {
                        $color = '#dc2626'; // 赤（多い）
                    } elseif ($remaining > ($max_remaining * 0.4)) {
                        $color = '#f59e0b'; // 黄（中程度）
                    }
                ?>
                <div style="border:1px solid #d1d5db; border-radius:6px; padding:12px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <div style="font-weight:bold; margin-bottom:8px; color:#1f2937; font-size:1em;">
                        <?php echo htmlspecialchars($player); ?>
                    </div>
                    <div style="font-size:2em; font-weight:bold; color:<?php echo $color; ?>; margin-bottom:10px; text-align:center;">
                        <?php echo $remaining; ?>
                    </div>
                    <div style="background:#e5e7eb; border-radius:4px; height:10px; overflow:hidden; margin-bottom:8px;">
                        <div style="background:<?php echo $color; ?>; height:100%; width:<?php echo $progress_percent; ?>%; transition:width 0.3s ease;"></div>
                    </div>
                    <div style="font-size:0.85em; color:#6b7280; text-align:center;">
                        残り試合数 / 最大 <?php echo $max_remaining; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- 未対戦組合せテーブル -->
    <h2>未対戦組合せ一覧</h2>
    
    <!-- 検索フォーム -->
    <div style="background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:20px;">
        <h3 style="margin-top:0; color:#374151; font-size:1.1em;">選手でフィルタリング</h3>
        <form method="POST" id="filter-form">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:12px;">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div>
                        <label for="filter_player_<?php echo $i; ?>" style="display:block; margin-bottom:6px; font-weight:bold; color:#374151; font-size:0.95em;">
                            選手 <?php echo $i; ?>
                        </label>
                        <select id="filter_player_<?php echo $i; ?>" name="filter_player_<?php echo $i; ?>" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; font-size:0.95em;">
                            <option value="">選択なし</option>
                            <?php foreach ($participants as $player): ?>
                                <option value="<?php echo htmlspecialchars($player); ?>" <?php echo (isset($selected_players[$i-1]) && $selected_players[$i-1] === $player) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($player); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn" style="padding:10px 16px; font-size:0.95em;">フィルタリング</button>
                <a href="unplayed_combinations.php" class="btn" style="padding:10px 16px; font-size:0.95em; background-color:#6b7280;">リセット</a>
            </div>
        </form>
    </div>

    <?php if (!empty($selected_players)): ?>
    <div style="background:#dbeafe; border:1px solid #93c5fd; border-radius:8px; padding:12px; margin-bottom:15px;">
        <p style="margin:0; color:#1e40af;">
            <strong>フィルタ中:</strong>
            <?php foreach ($selected_players as $p): ?>
                <span style="display:inline-block; background:#3b82f6; color:#fff; padding:4px 8px; border-radius:4px; margin-right:6px;">
                    <?php echo htmlspecialchars($p); ?>
                </span>
            <?php endforeach; ?>
        </p>
    </div>
    <?php endif; ?>

    <p style="color:#666; margin-bottom:15px;">
        表示中: <strong><?php echo count($filtered_unplayed); ?></strong> / 全 <?php echo count($unplayed); ?> 組合せ
    </p>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px;">
        <?php if (empty($filtered_unplayed)): ?>
            <div style="grid-column:1/-1; padding:20px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; text-align:center;">
                <p style="color:#991b1b; margin:0;">
                    <?php if (!empty($selected_players)): ?>
                        条件に合う未対戦組合せがありません。
                    <?php else: ?>
                        未対戦の組み合わせはありません。
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($filtered_unplayed as $key => $assign): ?>
                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <?php 
                        // 選択された選手と選択されていない選手を分けてソート
                        $winds_order = ['東', '南', '西', '北'];
                        $selected_winds = [];
                        $unselected_winds = [];
                        
                        foreach ($winds_order as $wind) {
                            $player_name = $assign[$wind] ?? '';
                            if (in_array($player_name, $selected_players)) {
                                $selected_winds[] = $wind;
                            } else {
                                $unselected_winds[] = $wind;
                            }
                        }
                        
                        // 選択された選手を上に、選択されていない選手を下に表示
                        $sorted_winds = array_merge($selected_winds, $unselected_winds);
                        ?>
                        <?php foreach ($sorted_winds as $wind): ?>
                            <?php 
                            $player_name = $assign[$wind] ?? '';
                            $is_selected = in_array($player_name, $selected_players);
                            $bg_color = $is_selected ? '#fef08a' : '#f3f4f6';
                            $border_color = $is_selected ? '#f59e0b' : 'transparent';
                            $font_weight = $is_selected ? 'bold' : 'normal';
                            ?>
                            <div style="padding:8px 12px; background:<?php echo $bg_color; ?>; border-radius:6px; text-align:center; border:2px solid <?php echo $border_color; ?>; font-weight:<?php echo $font_weight; ?>;">
                                <span style="color:1f2937; font-size:1.05em;">
                                    <?php echo htmlspecialchars($player_name); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div style="margin-top:18px;">
        <a href="home.php" class="btn">ホームに戻る</a>
    </div>
<?php include 'includes/footer.php'; ?>