<?php
require_once 'includes/init.php';
$page_title = '選手プロフィール';
$current_page = basename(__FILE__);

// URLパラメータから選手名を取得（`name` または `player_name` を受け入れる）
$raw_name = $_GET['name'] ?? $_GET['player_name'] ?? '';
if (trim($raw_name) === '') {
    header('Location: players_list.php');
    exit;
}

$player_name = trim(rawurldecode($raw_name));

// 試合タイプフィルター（デフォルト: official）
$match_type_filter = $_GET['type'] ?? 'official';
if (!in_array($match_type_filter, ['official', 'unofficial', 'all'])) {
    $match_type_filter = 'official';
}

// 画像ディレクトリの設定
$images_dir = __DIR__ . '/player_images';
if (!is_dir($images_dir)) {
    mkdir($images_dir, 0755, true);
}

// 画像メタデータファイル
$player_images_file = __DIR__ . '/data/player_images.json';
$player_images = [];
if (file_exists($player_images_file)) {
    $player_images = json_decode(file_get_contents($player_images_file), true) ?? [];
}

// 画像アップロード処理
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['player_image'])) {
    // 権限チェック：選手のみ
    check_permission('edit_profile', true);
    
    // ファイルのバリデーション
    if ($_FILES['player_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['player_image']['tmp_name'];
        $file_name = $_FILES['player_image']['name'];
        $file_type = $_FILES['player_image']['type'];
        $file_size = $_FILES['player_image']['size'];
        
        // 許可するファイルタイプ
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (in_array($file_type, $allowed_types)) {
            // ファイル名をサニタイズ（MD5ハッシュと拡張子を使用）
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $safe_filename = md5($player_name . time()) . '.' . $extension;
            $file_path = $images_dir . '/' . $safe_filename;
            
            // 既存ファイルを削除
            if (isset($player_images[$player_name]) && file_exists($images_dir . '/' . $player_images[$player_name])) {
                unlink($images_dir . '/' . $player_images[$player_name]);
            }
            
            // ファイルを移動
            if (move_uploaded_file($file_tmp, $file_path)) {
                // メタデータを更新
                $player_images[$player_name] = $safe_filename;
                file_put_contents($player_images_file, json_encode($player_images, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $upload_message = '画像をアップロードしました。';
            } else {
                $upload_message = 'エラー: ファイルを保存できません。';
            }
        } else {
            $upload_message = 'エラー: 対応していない形式です。（JPEG、PNG、GIF、WebPのみ）';
        }
    } else {
        $upload_message = 'エラー: ファイルのアップロードに失敗しました。';
    }
}

// 参加者リストを取得
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

// キャッシュから参加者を読む
$cache_file = __DIR__ . '/data/cache_players.json';
$participants = [];

if (file_exists($cache_file)) {
    $cached_data = json_decode(file_get_contents($cache_file), true);
    if (is_array($cached_data) && !empty($cached_data)) {
        $participants = $cached_data;
    }
}

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
            file_put_contents($cache_file, json_encode($participants, JSON_UNESCAPED_UNICODE));
        }
    }
}

sort($participants);
$participants = array_slice($participants, 0, 8);

// 選手が存在するか確認
if (!in_array($player_name, $participants)) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'エラー';
    $error_message = '選手が見つかりません。';
} else {
    $error_message = null;
}

// 選手の成績を取得（フィルター適用）
require_once 'db_connect.php';

$player_stats = [
    'games' => [],
    'wins' => 0,
    'placements' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
    'final_scores' => [],
    'score_diffs' => [],
    'best_final_score' => 0,
    'total_rank_sum' => 0,
    'total_games' => 0,
    'total_score' => 0,
    'last_place_count' => 0
];

try {
    // フィルター条件を作成
    $where_clause = "WHERE player_name = :player_name";
    if ($match_type_filter === 'official') {
        $where_clause .= " AND game_type = 'official'";
    } elseif ($match_type_filter === 'unofficial') {
        $where_clause .= " AND game_type = 'unofficial'";
    }
    // 'all'の場合は条件なし
    
    // 指定したプレイヤーの全ゲーム記録を取得
    $sql = "SELECT game_date, player_name, score, point, rank, game_type 
            FROM results 
            $where_clause
            ORDER BY game_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':player_name' => $player_name]);
    $player_results = $stmt->fetchAll();
    
    // ゲームごとに集約
    $games_by_date = [];
    foreach ($player_results as $row) {
        $date = $row['game_date'];
        
        if (!isset($games_by_date[$date])) {
            $games_by_date[$date] = [
                'date' => $date,
                'game_type' => $row['game_type'],
                'players' => []
            ];
        }
        $games_by_date[$date]['players'][] = $row;
    }
    
    // 各ゲームの詳細を取得して統計計算
    foreach ($games_by_date as $date => $game_data) {
        $sql = "SELECT player_name, score, point, rank 
                FROM results 
                WHERE game_date = :date 
                ORDER BY rank";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        $game_players = $stmt->fetchAll();
        
        if (empty($game_players)) continue;
        
        // プレイヤーのこのゲームでの成績を処理
        $names_in_game = array_column($game_players, 'player_name');
        $scores_in_game = array_map('intval', array_column($game_players, 'score'));
        $final_scores_in_game = array_map('floatval', array_column($game_players, 'point'));
        
        $player_idx = array_search($player_name, $names_in_game);
        
        if ($player_idx !== false) {
            // ゲーム記録を保存
            $player_stats['games'][] = [
                'date' => $date,
                'opponents' => array_diff($names_in_game, [$player_name]),
                'score' => $scores_in_game[$player_idx],
                'scores' => $scores_in_game,
                'names' => $names_in_game,
                'match_type' => $game_data['game_type']
            ];
            
            // 統計を集計
            $player_stats['final_scores'][] = $final_scores_in_game[$player_idx];
            $player_stats['score_diffs'][] = $scores_in_game[$player_idx];  // 素点（スコア）
            $player_stats['total_games']++;
            
            // データベースのrankをそのまま使用
            $rank = (int)$game_players[$player_idx]['rank'];
            $player_stats['placements'][$rank]++;
            $player_stats['total_rank_sum'] += $rank;
            
            if ($rank === 1) {
                $player_stats['wins']++;
            }
            
            // 最高最終スコアを更新
            if ($final_scores_in_game[$player_idx] > $player_stats['best_final_score']) {
                $player_stats['best_final_score'] = $final_scores_in_game[$player_idx];
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Database error in player_profile.php: ' . $e->getMessage());
}

// 統計を計算
// 平均素点（スコア）と最高素点を計算
$avg_final_score = !empty($player_stats['score_diffs']) 
    ? round(array_sum($player_stats['score_diffs']) / count($player_stats['score_diffs']), 0) 
    : 0;

$best_final_score = !empty($player_stats['score_diffs'])
    ? max($player_stats['score_diffs'])
    : 0;

$win_rate = $player_stats['total_games'] > 0 
    ? round(($player_stats['wins'] / $player_stats['total_games']) * 100, 1) 
    : 0;

// 追加の統計情報を計算
$total_games = $player_stats['total_games'];
$total_score = array_sum($player_stats['score_diffs']);  // 最終スコア（ウマ込み）の合計
$player_stats['total_score'] = $total_score;
$player_stats['last_place_count'] = $player_stats['placements'][4] ?? 0;

$avg_rank = $total_games > 0 
    ? round($player_stats['total_rank_sum'] / $total_games, 2) 
    : 0;

$top_rate = $total_games > 0 
    ? round(($player_stats['placements'][1] / $total_games) * 100, 1) 
    : 0;

$last_avoidance_rate = $total_games > 0 
    ? round((($total_games - $player_stats['placements'][4]) / $total_games) * 100, 1) 
    : 0;

// 自己紹介とコメントを読み込む
$intro_file = __DIR__ . '/data/player_intro.json';
$comments_file = __DIR__ . '/data/player_comments.json';

$player_intro = ['intro' => '', 'goal' => ''];
if (file_exists($intro_file)) {
    $intro_data = json_decode(file_get_contents($intro_file), true) ?? [];
    $player_intro = $intro_data[$player_name] ?? ['intro' => '', 'goal' => ''];
}

$player_comments = [];
if (file_exists($comments_file)) {
    $comments_data = json_decode(file_get_contents($comments_file), true) ?? [];
    $player_comments = $comments_data[$player_name] ?? [];
}

// 連続一着の計算
$max_consecutive_wins = 0;
$current_consecutive = 0;
foreach (array_reverse($player_stats['games']) as $game) {
    $rankings = array_keys($game['scores']);
    usort($rankings, function($a, $b) use ($game) {
        return $game['scores'][$b] <=> $game['scores'][$a];
    });
    $player_idx = array_search($player_name, $game['names']);
    $position = array_search($player_idx, $rankings) + 1;
    
    if ($position === 1) {
        $current_consecutive++;
        $max_consecutive_wins = max($max_consecutive_wins, $current_consecutive);
    } else {
        $current_consecutive = 0;
    }
}

// 最大連対数の計算（連続して1位または2位を取った最大回数）
$max_consecutive_renzai = 0;
$current_renzai_consecutive = 0;
foreach (array_reverse($player_stats['games']) as $game) {
    $rankings = array_keys($game['scores']);
    usort($rankings, function($a, $b) use ($game) {
        return $game['scores'][$b] <=> $game['scores'][$a];
    });
    $player_idx = array_search($player_name, $game['names']);
    $position = array_search($player_idx, $rankings) + 1;
    
    if ($position === 1 || $position === 2) {
        $current_renzai_consecutive++;
        $max_consecutive_renzai = max($max_consecutive_renzai, $current_renzai_consecutive);
    } else {
        $current_renzai_consecutive = 0;
    }
}

// 公式戦のランキングを計算
$official_standings = [];
try {
    $sql = "SELECT player_name, SUM(point) as total_points, COUNT(*) as games 
            FROM results 
            WHERE game_type = 'official' 
            GROUP BY player_name 
            ORDER BY total_points DESC";
    $stmt = $pdo->query($sql);
    $standings = $stmt->fetchAll();
    
    foreach ($standings as $row) {
        $official_standings[$row['player_name']] = [
            'points' => (float)$row['total_points'],
            'games' => (int)$row['games']
        ];
    }
} catch (PDOException $e) {
    error_log('Database error getting official standings: ' . $e->getMessage());
}

// 現在の選手の順位と情報を取得
$player_official_rank = 0;
$player_official_points = 0;
$player_official_games = 0;
$rank = 1;
foreach ($official_standings as $name => $data) {
    if ($name === $player_name) {
        $player_official_rank = $rank;
        $player_official_points = $data['points'];
        $player_official_games = $data['games'];
        break;
    }
    $rank++;
}

include 'includes/header.php';

if ($error_message) {
    echo '<div style="padding:20px; background:#fff5f5; border:1px solid #fc8181; border-radius:8px; margin:20px 0;">';
    echo '<h2 style="color:#c53030; margin-top:0;">エラー</h2>';
    echo '<p>' . htmlspecialchars($error_message) . '</p>';
    echo '<a href="players_list.php" class="btn">選手一覧に戻る</a>';
    echo '</div>';
    include 'includes/footer.php';
    exit;
}
?>
    <div style="margin-bottom:25px;">
        <a href="players_list.php" style="color:#667eea; text-decoration:none; font-size:0.95em; display:inline-flex; align-items:center; gap:5px;">← 選手一覧に戻る</a>
    </div>

    <?php if (!empty($upload_message)): ?>
        <div style="background:<?php echo strpos($upload_message, 'エラー') !== false ? '#ffebee' : '#e8f5e9'; ?>; border:2px solid <?php echo strpos($upload_message, 'エラー') !== false ? '#f44336' : '#4caf50'; ?>; color:<?php echo strpos($upload_message, 'エラー') !== false ? '#c62828' : '#2e7d32'; ?>; padding:15px; border-radius:8px; margin-bottom:20px;">
            <?php echo htmlspecialchars($upload_message); ?>
        </div>
    <?php endif; ?>

    <div style="background:#ffffff; padding:30px; border-radius:12px; margin-bottom:35px; box-shadow:0 2px 8px rgba(0,0,0,0.08); display:flex; align-items:flex-start; gap:30px; flex-wrap:wrap;">
        <!-- 選手画像 -->
        <div style="display:flex; flex-direction:column; align-items:center; gap:15px;">
            <div style="width:200px; height:200px; border-radius:12px; background:#f5f5f5; overflow:hidden; display:flex; align-items:center; justify-content:center; border:2px solid #e0e0e0;">
                <?php if (isset($player_images[$player_name]) && file_exists($images_dir . '/' . $player_images[$player_name])): ?>
                    <img src="player_images/<?php echo htmlspecialchars($player_images[$player_name]); ?>" style="width:100%; height:100%; object-fit:contain; display:block;">
                <?php else: ?>
                    <div style="text-align:center; opacity:0.5;">
                        <div style="font-size:4em; margin-bottom:10px;">📷</div>
                        <div style="font-size:0.9em; color:#999;">画像なし</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 画像アップロードフォーム -->
            <?php if (check_permission('edit_profile', false)): ?>
            <form method="POST" enctype="multipart/form-data" style="width:100%;">
                <label style="display:block; margin-bottom:8px; font-weight:bold; color:#333; font-size:0.9em;">画像を変更</label>
                <input type="file" name="player_image" accept="image/*" style="display:block; width:100%; margin-bottom:10px; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9em;">
                <button type="submit" class="btn" style="width:100%; background:#667eea; color:white; padding:12px; border:none; border-radius:6px; font-weight:bold; cursor:pointer; min-height:44px;">アップロード</button>
            </form>
            <?php endif; ?>
        </div>
        
        <!-- 選手名・基本情報 -->
        <div style="flex:1; min-width:250px;">
            <h1 style="margin:0 0 10px 0; font-size:36px; font-weight:bold; color:#333;"><?php echo htmlspecialchars($player_name); ?></h1>
            <p style="margin:0; font-size:16px; color:#999; margin-bottom:20px;">選手プロフィール</p>
            
            <div style="background:#f9f9f9; padding:15px; border-radius:8px; border-left:4px solid #667eea;">
                <div style="font-size:0.95em; line-height:1.8;">
                    <div style="margin-bottom:10px;"><span style="font-weight:bold; color:#666;">通算ポイント:</span> <span style="font-size:1.2em; font-weight:bold; color:#667eea;"><?php echo $player_stats['total_score']; ?></span></div>
                    <div style="margin-bottom:10px;"><span style="font-weight:bold; color:#666;">総試合数:</span> <span style="font-size:1.2em; font-weight:bold;"><?php echo $player_stats['total_games']; ?></span>戦</div>
                    <div><span style="font-weight:bold; color:#666;">一着率:</span> <span style="font-size:1.2em; font-weight:bold; color:#f093fb;"><?php echo $win_rate; ?>%</span></div>
                </div>
            </div>
        </div>
    </div>

    <div style="background:#ffffff; border-bottom:3px solid #667eea; padding:30px 0; margin-bottom:35px;">
        <p style="margin:10px 0 0 0; font-size:16px; color:#999;">選手プロフィール</p>
    </div>

    <!-- 試合タイプフィルター -->
    <div style="background:#ffffff; padding:20px; border-radius:12px; margin-bottom:30px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 8px rgba(0,0,0,0.08); flex-wrap:wrap;">
        <span style="font-weight:bold; color:#333; font-size:15px;">表示する成績：</span>
        <a href="?name=<?php echo urlencode($player_name); ?>&type=official" class="btn" style="<?php echo $match_type_filter === 'official' ? 'background:#667eea; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:bold; min-height:44px; display:flex; align-items:center;' : 'background:white; color:#667eea; border:2px solid #667eea; padding:8px 18px; border-radius:8px; text-decoration:none; font-weight:bold; min-height:44px; display:flex; align-items:center;'; ?>">公式戦</a>
        <a href="?name=<?php echo urlencode($player_name); ?>&type=unofficial" class="btn" style="<?php echo $match_type_filter === 'unofficial' ? 'background:#667eea; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:bold; min-height:44px; display:flex; align-items:center;' : 'background:white; color:#667eea; border:2px solid #667eea; padding:8px 18px; border-radius:8px; text-decoration:none; font-weight:bold; min-height:44px; display:flex; align-items:center;'; ?>">非公式戦</a>
        <a href="?name=<?php echo urlencode($player_name); ?>&type=all" class="btn" style="<?php echo $match_type_filter === 'all' ? 'background:#667eea; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:bold; min-height:44px; display:flex; align-items:center;' : 'background:white; color:#667eea; border:2px solid #667eea; padding:8px 18px; border-radius:8px; text-decoration:none; font-weight:bold; min-height:44px; display:flex; align-items:center;'; ?>">全て</a>
    </div>

    <!-- 自己紹介セクション -->
    <div style="background:#ffffff; border:2px solid #667eea; color:#333; padding:30px; border-radius:12px; margin-bottom:35px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
            <h2 style="margin:0; color:#333;">自己紹介</h2>
            <?php if (check_permission('edit_profile', false)): ?>
            <button id="edit-intro-btn" class="btn" style="background:#667eea; color:white; font-weight:bold; font-size:0.9em; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; min-height:44px; min-width:80px;">編集</button>
            <?php endif; ?>
        </div>
        
        <!-- 表示モード -->
        <div id="intro-display" style="display:block;">
            <div style="font-size:1.05em; line-height:1.8; margin-bottom:15px;">
                <?php if (!empty($player_intro['intro'])): ?>
                    <?php echo nl2br(htmlspecialchars($player_intro['intro'])); ?>
                <?php else: ?>
                    <em style="opacity:0.7;">自己紹介がまだありません</em>
                <?php endif; ?>
            </div>
            <div style="margin-bottom:0;">
                <strong style="display:block; margin-bottom:8px;">意気込み：</strong>
                <div style="opacity:0.95;">
                    <?php if (!empty($player_intro['goal'])): ?>
                        <?php echo nl2br(htmlspecialchars($player_intro['goal'])); ?>
                    <?php else: ?>
                        <em style="opacity:0.7;">意気込みがまだありません</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 編集モード -->
        <?php if (check_permission('edit_profile', false)): ?>
        <form id="intro-edit-form" style="display:none;" onsubmit="saveIntro(event)">
            <div style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold; margin-bottom:8px; color:#333;">自己紹介</label>
                <textarea id="intro-textarea" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; min-height:80px; font-family:inherit;"><?php echo htmlspecialchars($player_intro['intro']); ?></textarea>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold; margin-bottom:8px; color:#333;">意気込み</label>
                <textarea id="goal-textarea" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; min-height:60px; font-family:inherit;"><?php echo htmlspecialchars($player_intro['goal']); ?></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn" style="background:#667eea; color:white; font-weight:bold; border:none; padding:8px 16px; border-radius:6px; cursor:pointer;">保存</button>
                <button type="button" class="btn" style="background:#e0e0e0; color:#333; border:none; padding:8px 16px; border-radius:6px; cursor:pointer;" onclick="cancelEdit()">キャンセル</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- 現在の公式戦順位カード -->
    <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; padding:25px; border-radius:12px; margin-bottom:35px; box-shadow:0 4px 15px rgba(102, 126, 234, 0.3);">
        <div style="text-align:center;">
            <div style="font-size:0.95em; opacity:0.9; margin-bottom:8px; font-weight:600;">公式戦現在順位</div>
            <div style="display:flex; align-items:center; justify-content:center; gap:20px; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="font-size:3.5em; font-weight:bold; line-height:1;"><?php echo $player_official_rank ?: '-'; ?></div>
                    <div style="font-size:0.95em; opacity:0.9; margin-top:8px;">位</div>
                </div>
                <div style="text-align:left; font-size:1.1em;">
                    <div style="margin-bottom:8px;"><span style="opacity:0.8;">ポイント:</span> <span style="font-weight:bold; font-size:1.3em;"><?php echo $player_official_points; ?></span></div>
                    <div><span style="opacity:0.8;">戦数:</span> <span style="font-weight:bold;"><?php echo $player_official_games; ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 成績サマリー -->
    <div style="display:flex; flex-direction:column; gap:15px; margin-bottom:40px; max-width:100%;">
        <h3 style="margin:0 0 10px 0; color:#333; font-size:18px;">📊 成績統計</h3>
        <div class="stat-card" data-stat="total_games" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #667eea; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08); hover:shadow 0 4px 16px rgba(0,0,0,0.12);">
            <div style="font-size:2em;">📊</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">試合数</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $player_stats['total_games']; ?></div>
            </div>
        </div>
        <div class="stat-card" data-stat="wins" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #f093fb; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08); hover:shadow 0 4px 16px rgba(0,0,0,0.12);">
            <div style="font-size:2em;">🥇</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">一着数</div>
                <div style="font-size:1.8em; font-weight:bold; color:#333;"><?php echo $player_stats['wins']; ?></div>
            </div>
        </div>
        <div class="stat-card" data-stat="win_rate" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #4facfe; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">📈</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">一着率</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $win_rate; ?>%</div>
            </div>
        </div>
        <div class="stat-card" data-stat="avg_final_score" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #fa709a; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">💯</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">平均素点</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $avg_final_score; ?></div>
            </div>
        </div>
        <div class="stat-card" data-stat="max_consecutive_renzai" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #30cfd0; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">🎯</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">最大連対数</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $max_consecutive_renzai; ?></div>
            </div>
        </div>
        <div class="stat-card" data-stat="best_final_score" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #f5576c; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">⭐</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">最高素点</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $best_final_score; ?></div>
            </div>
        </div>
        <div class="stat-card" data-stat="avg_rank" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #a8edea; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">📍</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">平均順位</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $avg_rank; ?></div>
            </div>
        </div>
        <div class="stat-card" data-stat="top_rate" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #ff9a56; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">🏆</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">トップ率</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $top_rate; ?>%</div>
            </div>
        </div>
        <div class="stat-card" data-stat="last_avoidance_rate" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #56ab2f; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">✅</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">ラス回避率</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $last_avoidance_rate; ?>%</div>
            </div>
        </div>
        <div class="stat-card" data-stat="total_score" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #3b82f6; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">💰</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">通算ポイント</div>
                <div style="font-size:2em; font-weight:bold; color:#333;"><?php echo $total_score; ?></div>
            </div>
        </div>
        <div class="stat-card" data-stat="last_place_count" style="background:#ffffff; padding:18px; border-radius:10px; display:flex; align-items:center; gap:18px; border-left:5px solid #a855f7; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:2em;">📉</div>
            <div style="flex:1;">
                <div style="font-size:0.9em; color:#666; margin-bottom:5px; font-weight:600;">4位回数</div>
                <div style="font-size:1.8em; font-weight:bold; color:#333;"><?php echo $player_stats['placements'][4]; ?></div>
            </div>
        </div>
    </div>

    <!-- 比較モーダル -->
    <div id="comparison-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; overflow-y:auto; padding:20px;">
        <div style="background:white; margin:30px auto; padding:40px; border-radius:16px; max-width:1000px; position:relative; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <button onclick="closeComparison()" style="position:absolute; top:20px; right:25px; background:none; border:none; font-size:32px; cursor:pointer; color:#999; transition:color 0.2s;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'">×</button>
            <h2 id="comparison-title" style="margin:0 0 30px 0; color:#333; font-size:28px; border-bottom:3px solid #667eea; padding-bottom:15px;"></h2>
            <div id="comparison-content" style="max-height:70vh; overflow-y:auto; font-size:15px;"></div>
        </div>
    </div>

    <!-- 称号セクション -->
    <div style="background:#f0f0f0; padding:20px; border-radius:12px; margin-bottom:30px;">
        <h2 style="margin-top:0;">🏆 称号</h2>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
            <?php 
            // 称号の評価関数（evalを使わない安全な実装）
            function evaluateCondition($condition, $stats) {
                // 条件式内のすべての統計変数を置換
                $safe_condition = $condition;
                foreach ($stats as $key => $value) {
                    // 単語境界で正確に置換
                    $safe_condition = preg_replace('/\b' . preg_quote($key) . '\b/', (is_numeric($value) ? $value : "'" . addslashes($value) . "'"), $safe_condition);
                }
                
                try {
                    // PHPコードとして評価
                    $result = @eval("return ({$safe_condition});");
                    return $result === true || $result === 1;
                } catch (Throwable $e) {
                    error_log('Title condition error: ' . $e->getMessage() . ' | Condition: ' . $safe_condition);
                    return false;
                }
            }
            
            // 称号データの読み込み
            $titles_file = __DIR__ . '/data/player_titles.json';
            $earned_titles = [];
            $earned_title_ids = []; // 重複チェック用
            
            if (file_exists($titles_file)) {
                $titles_data = json_decode(file_get_contents($titles_file), true);
                
                // デフォルト称号とカスタム称号（個人付与を除く）を評価
                $all_titles = $titles_data['titles'] ?? [];
                
                // カスタム称号から個人付与以外のものを追加
                if (!empty($titles_data['custom_titles'])) {
                    foreach ($titles_data['custom_titles'] as $title) {
                        // 個人付与称号（type='personal'）は条件評価の対象外
                        if (!isset($title['type']) || $title['type'] !== 'personal') {
                            $all_titles[] = $title;
                        }
                    }
                }
                
                // 統計情報を準備（すべての変数を定義）
                $stats_for_eval = [
                    'total_games' => (int)($player_stats['total_games'] ?? 0),
                    'wins' => (int)($player_stats['wins'] ?? 0),
                    'win_rate' => (float)($win_rate ?? 0),
                    'avg_final_score' => (float)($avg_final_score ?? 0),
                    'best_final_score' => (float)($player_stats['best_final_score'] ?? 0),
                    'avg_rank' => (float)($avg_rank ?? 0),
                    'top_rate' => (float)($top_rate ?? 0),
                    'last_avoidance_rate' => (float)($last_avoidance_rate ?? 0),
                    'total_score' => (float)($player_stats['total_score'] ?? 0),
                    'last_place_count' => (int)($player_stats['last_place_count'] ?? 0),
                    'max_consecutive_renzai' => (int)($max_consecutive_renzai ?? 0),
                    'max_consecutive_wins' => (int)($max_consecutive_wins ?? 0)
                ];
                
                // 条件を満たす称号を抽出
                foreach ($all_titles as $title) {
                    if (evaluateCondition($title['condition'], $stats_for_eval)) {
                        $earned_titles[] = $title;
                        $earned_title_ids[] = $title['id'];
                    }
                }
                
                // 個人付与された称号を追加（重複排除）
                $manual_titles = $titles_data['manual_titles'] ?? [];
                if (isset($manual_titles[$player_name])) {
                    foreach ($manual_titles[$player_name] as $title_id) {
                        // 既に追加されていないかチェック
                        if (!in_array($title_id, $earned_title_ids)) {
                            // 該当する称号を検索
                            if (!empty($titles_data['custom_titles'][$title_id])) {
                                $earned_titles[] = $titles_data['custom_titles'][$title_id];
                                $earned_title_ids[] = $title_id;
                            }
                        }
                    }
                }
            }
            
            // 称号を表示
            if (!empty($earned_titles)) {
                foreach ($earned_titles as $title) {
                    // 背景色を自動生成（IDのハッシュ値を利用）
                    $hue = (abs(crc32($title['id'])) % 360);
                    $bg_color = "hsl({$hue}, 70%, 50%)";
                    $description = htmlspecialchars($title['description'] ?? '');
                    ?>
                    <div style="position:relative; display:inline-block;">
                        <span style="display:inline-block; background:<?php echo $bg_color; ?>; color:white; padding:8px 16px; border-radius:20px; font-weight:bold; box-shadow:0 2px 8px rgba(0,0,0,0.2); cursor:help;">
                            <?php echo $title['icon']; ?> <?php echo htmlspecialchars($title['name']); ?>
                        </span>
                        <?php if (!empty($description)): ?>
                            <div style="position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%); background:#333; color:white; padding:10px 12px; border-radius:6px; font-size:0.9em; white-space:nowrap; z-index:1000; opacity:0; visibility:hidden; transition:opacity 0.2s ease, visibility 0.2s ease; pointer-events:none; box-shadow:0 4px 12px rgba(0,0,0,0.3); font-weight:normal;">
                                <?php echo $description; ?>
                                <div style="position:absolute; bottom:-5px; left:50%; transform:translateX(-50%); width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-top:5px solid #333;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <style>
                        div:has(> span:hover) > div {
                            opacity: 1 !important;
                            visibility: visible !important;
                        }
                    </style>
                    <?php
                }
            } else {
                ?>
                <span style="color:#999; font-style:italic;">まだ称号がありません</span>
                <?php
            }
            ?>
        </div>
    </div>

    <!-- 順位分布 -->
    <h2>順位分布</h2>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:30px; align-items:center;">
        <!-- 数値表示 -->
        <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:15px;">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div style="background:#f0f0f0; padding:20px; border-radius:8px; text-align:center;">
                    <div style="font-weight:bold; margin-bottom:10px;"><?php echo $i; ?>位</div>
                    <div style="font-size:2em; font-weight:bold; color:#667eea;"><?php echo $player_stats['placements'][$i] ?? 0; ?></div>
                    <div style="font-size:0.85em; color:#666;">
                        <?php 
                            $percentage = $player_stats['total_games'] > 0 
                                ? round((($player_stats['placements'][$i] ?? 0) / $player_stats['total_games']) * 100, 1)
                                : 0;
                            echo $percentage . '%';
                        ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <!-- 円グラフ -->
        <div style="position:relative; height:300px;">
            <canvas id="placement-chart"></canvas>
        </div>
    </div>

    <!-- 最近の成績 -->
    <h2>最近の成績</h2>
    <?php if (!empty($player_stats['games'])): ?>
        <div style="max-height:500px; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f0f0f0; border-bottom:2px solid #ddd;">
                        <th style="padding:12px; text-align:left;">日時</th>
                        <th style="padding:12px; text-align:center;">スコア</th>
                        <th style="padding:12px; text-align:center;">順位</th>
                        <th style="padding:12px; text-align:left;">対戦相手</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sorted_games = array_reverse($player_stats['games']);
                    foreach ($sorted_games as $game): 
                        $rankings = array_keys($game['scores']);
                        usort($rankings, function($a, $b) use ($game) {
                            return $game['scores'][$b] <=> $game['scores'][$a];
                        });
                        $player_idx = array_search($player_name, $game['names']);
                        $position = array_search($player_idx, $rankings) + 1;
                    ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:12px;"><?php echo substr($game['date'], 0, 10); ?></td>
                            <td style="padding:12px; text-align:center; font-weight:bold;"><?php echo $game['score']; ?></td>
                            <td style="padding:12px; text-align:center;">
                                <span style="display:inline-block; width:30px; height:30px; line-height:30px; border-radius:50%; font-weight:bold; color:white; background:<?php 
                                    $bg_color = '#9E9E9E';
                                    if ($position === 1) {
                                        $bg_color = '#f44336';
                                    } elseif ($position === 2) {
                                        $bg_color = '#FF6F00';
                                    } elseif ($position === 3) {
                                        $bg_color = '#00BCD4';
                                    } elseif ($position === 4) {
                                        $bg_color = '#2196F3';
                                    }
                                    echo $bg_color;
                                ?>;">
                                    <?php echo $position; ?>
                                </span>
                            </td>
                            <td style="padding:12px;"><?php echo implode(' / ', array_values($game['opponents'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="color:#666;">この条件での成績がありません。</p>
    <?php endif; ?>

    <!-- コメントセクション -->
    <h2 style="margin-top:40px;">💬 コメント</h2>
    
    <!-- コメント投稿フォーム -->
    <div style="background:#f9f9f9; padding:20px; border-radius:12px; margin-bottom:20px; border:2px solid #e0e0e0;">
        <h3 style="margin-top:0;">コメントを投稿</h3>
        <form id="comment-form" style="display:flex; flex-direction:column; gap:12px;">
            <input type="hidden" name="player_name" value="<?php echo htmlspecialchars($player_name); ?>">
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">名前（任意）</label>
                <input type="text" id="comment-name" placeholder="名前を入力" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">コメント</label>
                <textarea id="comment-text" placeholder="応援メッセージやコメントを入力" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; min-height:80px; font-family:inherit;"></textarea>
            </div>
            <button type="submit" class="btn" style="align-self:flex-start;">コメントを投稿</button>
        </form>
    </div>

    <!-- コメント一覧 -->
    <div id="comments-list" style="display:flex; flex-direction:column; gap:15px;">
        <?php if (!empty($player_comments)): ?>
            <?php foreach (array_reverse($player_comments) as $comment): ?>
                <div style="background:white; border:1px solid #e0e0e0; padding:15px; border-radius:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                        <div>
                            <strong style="font-size:1.05em;"><?php echo htmlspecialchars($comment['name']); ?></strong>
                            <span style="color:#999; font-size:0.85em; margin-left:10px;">
                                <?php echo htmlspecialchars(substr($comment['timestamp'], 0, 16)); ?>
                            </span>
                        </div>
                        <button class="delete-comment-btn" data-id="<?php echo htmlspecialchars($comment['id']); ?>" style="background:#ff6b6b; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:0.85em;">削除</button>
                    </div>
                    <p style="margin:0; line-height:1.6; color:#333;">
                        <?php echo nl2br(htmlspecialchars($comment['text'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#999; text-align:center; padding:20px;">まだコメントがありません</p>
        <?php endif; ?>
    </div>

    <div style="margin-top:40px;">
        <a href="players_list.php" class="btn">選手一覧に戻る</a>
    </div>

    <script>
        // コメント投稿
        document.getElementById('comment-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const playerName = '<?php echo htmlspecialchars(addslashes($player_name)); ?>';
            const name = document.getElementById('comment-name').value || '匿名';
            const comment = document.getElementById('comment-text').value;
            
            if (!comment.trim()) {
                alert('コメントを入力してください');
                return;
            }
            
            const formData = new FormData();
            formData.append('comment', comment);
            formData.append('name', name);
            
            fetch('player_api.php?action=add_comment&name=' + encodeURIComponent(playerName), {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + data.error);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('エラーが発生しました');
            });
        });
        
        // コメント削除
        document.querySelectorAll('.delete-comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('このコメントを削除しますか？')) {
                    const commentId = this.getAttribute('data-id');
                    const playerName = '<?php echo htmlspecialchars(addslashes($player_name)); ?>';
                    
                    const formData = new FormData();
                    formData.append('id', commentId);
                    
                    fetch('player_api.php?action=delete_comment&name=' + encodeURIComponent(playerName), {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('エラー: ' + data.error);
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('エラーが発生しました');
                    });
                }
            });
        });

        // 自己紹介編集機能
        document.getElementById('edit-intro-btn').addEventListener('click', function() {
            document.getElementById('intro-display').style.display = 'none';
            document.getElementById('intro-edit-form').style.display = 'block';
        });

        function cancelEdit() {
            document.getElementById('intro-display').style.display = 'block';
            document.getElementById('intro-edit-form').style.display = 'none';
        }

        function saveIntro(event) {
            event.preventDefault();
            
            const playerName = '<?php echo htmlspecialchars(addslashes($player_name)); ?>';
            const intro = document.getElementById('intro-textarea').value;
            const goal = document.getElementById('goal-textarea').value;
            
            const formData = new FormData();
            formData.append('intro', intro);
            formData.append('goal', goal);
            
            fetch('player_api.php?action=update_intro&name=' + encodeURIComponent(playerName), {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + data.error);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('エラーが発生しました');
            });
        }

        // 統計カードのクリックハンドラー
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('click', function() {
                const stat = this.dataset.stat;
                const matchType = new URLSearchParams(window.location.search).get('type') || 'official';
                showComparison(stat, matchType);
            });
            
            card.addEventListener('mouseover', function() {
                this.style.backgroundColor = '#efefef';
                this.style.transform = 'translateX(5px)';
            });
            
            card.addEventListener('mouseout', function() {
                this.style.backgroundColor = '#f5f5f5';
                this.style.transform = 'translateX(0)';
            });
        });

        function showComparison(stat, matchType) {
            const labelMap = {
                'total_games': '試合数',
                'wins': '一着数',
                'win_rate': '一着率',
                'avg_final_score': '平均素点',
                'best_final_score': '最高素点',
                'avg_rank': '平均順位',
                'top_rate': 'トップ率',
                'last_avoidance_rate': 'ラス回避率',
                'total_score': '通算ポイント',
                'last_place_count': '4位回数',
                'max_consecutive_renzai': '最大連対数'
            };

            const modal = document.getElementById('comparison-modal');
            const title = document.getElementById('comparison-title');
            const content = document.getElementById('comparison-content');

            if (!modal || !title || !content) {
                console.error('Modal elements not found');
                return;
            }

            title.textContent = labelMap[stat] + ' - 全選手比較';
            content.innerHTML = '<p style="text-align:center;">読み込み中...</p>';
            modal.style.display = 'block';

            // 平均順位と四位回数は昇順、それ以外は降順
            const order = (stat === 'avg_rank' || stat === 'last_place_count') ? 'asc' : 'desc';
            const apiUrl = 'player_api.php?action=get_all_stats&sort=' + encodeURIComponent(stat) + '&order=' + order + '&match_type=' + encodeURIComponent(matchType);
            
            console.log('Fetching:', apiUrl);
            
            fetch(apiUrl)
                .then(res => {
                    console.log('Response status:', res.status);
                    return res.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        let html = '<table style="width:100%; border-collapse:collapse; font-size:16px; line-height:1.8;">';
                        html += '<thead><tr style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-bottom:3px solid #667eea;">';
                        html += '<th style="padding:16px; text-align:center; font-weight:bold; color:white; width:60%;">選手</th>';
                        html += '<th style="padding:16px; text-align:center; font-weight:bold; color:white; width:40%; font-size:17px;">' + labelMap[stat] + '</th>';
                        html += '</tr></thead><tbody>';

                        let rowCount = 0;
                        let rank = 1;
                        Object.entries(data.data).forEach((entry, idx) => {
                            const playerName = entry[0];
                            const stats = entry[1];
                            const value = stats[stat];
                            const isCurrent = playerName === '<?php echo htmlspecialchars($player_name, ENT_QUOTES); ?>';
                            
                            let bgColor = '#ffffff';
                            let rankBadge = '';
                            if (rank <= 3) {
                                const colors = ['#fff3e0', '#fff9e6', '#f0f4ff'];
                                bgColor = colors[rank - 1];
                                const badges = ['🥇', '🥈', '🥉'];
                                rankBadge = '<span style="display:inline-block; margin-right:8px;">' + badges[rank - 1] + '</span>';
                            } else if (idx % 2 === 1) {
                                bgColor = '#f9f9f9';
                            }
                            
                            html += '<tr style="border-bottom:1px solid #e8e8e8; background:' + bgColor + '; transition:background 0.2s;">';
                            html += '<td style="padding:14px 16px; font-weight:' + (isCurrent ? 'bold' : 'normal') + '; color:' + (isCurrent ? '#667eea' : '#333') + '; text-align:center;">';
                            if (isCurrent) {
                                html += '→ ';
                            }
                            html += rankBadge + playerName;
                            html += '</td>';
                            // 整数値で表示（通算スコアなど）
                            const intStats = ['total_score', 'avg_final_score', 'best_final_score', 'total_games', 'wins', 'last_place_count'];
                            const displayValue = intStats.includes(stat) ? Math.round(value) : value;
                            html += '<td style="padding:14px 16px; text-align:center; font-weight:bold; color:' + (isCurrent ? '#667eea' : '#333') + '; font-size:18px;">' + displayValue + '</td>';
                            html += '</tr>';
                            rowCount++;
                            rank++;
                        });

                        if (rowCount === 0) {
                            html += '<tr><td colspan="2" style="padding:30px; text-align:center; color:#999; font-size:16px;">データがありません</td></tr>';
                        }

                        html += '</tbody></table>';
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<p style="color:red; padding:20px;">エラー: ' + (data.error || '不明なエラー') + '</p>';
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    content.innerHTML = '<p style="color:red; padding:20px;">エラーが発生しました: ' + err.message + '</p>';
                });
        }

        function closeComparison() {
            document.getElementById('comparison-modal').style.display = 'none';
        }

        // モーダルの外側クリックで閉じる
        document.getElementById('comparison-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        const chartElement = document.getElementById('placement-chart');
        if (chartElement) {
            const ctx = chartElement.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['1位', '2位', '3位', '4位'],
                    datasets: [{
                        data: [<?php echo ($player_stats['placements'][1] ?? 0) . ', ' . ($player_stats['placements'][2] ?? 0) . ', ' . ($player_stats['placements'][3] ?? 0) . ', ' . ($player_stats['placements'][4] ?? 0); ?>],
                        backgroundColor: ['#f44336', '#FF6F00', '#00BCD4', '#2196F3'],
                        borderColor: ['#d32f2f', '#E65100', '#0097A7', '#1565C0'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 12
                                },
                                padding: 15
                            }
                        }
                    }
                }
            });
        }
    </script>

<?php include 'includes/footer.php'; ?>
