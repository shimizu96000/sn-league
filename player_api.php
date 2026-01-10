<?php
// player_api.php - 選手情報のAPI処理

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php';

$action = $_GET['action'] ?? '';
$player_name = isset($_GET['name']) ? htmlspecialchars(urldecode($_GET['name']), ENT_QUOTES, 'UTF-8') : '';

$intro_file = __DIR__ . '/data/player_intro.json';
$comments_file = __DIR__ . '/data/player_comments.json';

// 自己紹介を取得
if ($action === 'get_intro') {
    if (file_exists($intro_file)) {
        $intro_data = json_decode(file_get_contents($intro_file), true) ?? [];
        $player_intro = $intro_data[$player_name] ?? ['intro' => '', 'goal' => ''];
        echo json_encode(['success' => true, 'data' => $player_intro], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'ファイルが見つかりません'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 自己紹介を更新（POST）
if ($action === 'update_intro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 権限チェック：選手のみ
    if (!check_permission('edit_profile', false)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'アクセス権限がありません'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $intro = isset($_POST['intro']) ? htmlspecialchars($_POST['intro'], ENT_QUOTES, 'UTF-8') : '';
    $goal = isset($_POST['goal']) ? htmlspecialchars($_POST['goal'], ENT_QUOTES, 'UTF-8') : '';
    
    $intro_data = [];
    if (file_exists($intro_file)) {
        $intro_data = json_decode(file_get_contents($intro_file), true) ?? [];
    }
    
    $intro_data[$player_name] = ['intro' => $intro, 'goal' => $goal];
    
    if (file_put_contents($intro_file, json_encode($intro_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true, 'message' => '更新しました'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'ファイル保存に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// コメント一覧を取得
if ($action === 'get_comments') {
    if (file_exists($comments_file)) {
        $comments_data = json_decode(file_get_contents($comments_file), true) ?? [];
        $player_comments = $comments_data[$player_name] ?? [];
        echo json_encode(['success' => true, 'data' => $player_comments], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'ファイルが見つかりません'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// コメントを追加（POST）
if ($action === 'add_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_text = isset($_POST['comment']) ? htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8') : '';
    $comment_name = isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : '匿名';
    
    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'error' => 'コメントが空です'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $comments_data = [];
    if (file_exists($comments_file)) {
        $comments_data = json_decode(file_get_contents($comments_file), true) ?? [];
    }
    
    if (!isset($comments_data[$player_name])) {
        $comments_data[$player_name] = [];
    }
    
    $new_comment = [
        'id' => uniqid(),
        'name' => $comment_name,
        'text' => $comment_text,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $comments_data[$player_name][] = $new_comment;
    
    if (file_put_contents($comments_file, json_encode($comments_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true, 'message' => 'コメントを追加しました', 'comment' => $new_comment], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'ファイル保存に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// コメントを削除（POST）
if ($action === 'delete_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 権限チェック：選手のみ
    if (!check_permission('edit_profile', false)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'アクセス権限がありません'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $comment_id = isset($_POST['id']) ? $_POST['id'] : '';
    
    $comments_data = [];
    if (file_exists($comments_file)) {
        $comments_data = json_decode(file_get_contents($comments_file), true) ?? [];
    }
    
    if (isset($comments_data[$player_name])) {
        $comments_data[$player_name] = array_filter($comments_data[$player_name], function($c) use ($comment_id) {
            return $c['id'] !== $comment_id;
        });
        $comments_data[$player_name] = array_values($comments_data[$player_name]);
        
        if (file_put_contents($comments_file, json_encode($comments_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => 'コメントを削除しました'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => 'ファイル保存に失敗しました'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'コメントが見つかりません'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 全選手の統計情報を取得（比較用）
if ($action === 'get_all_stats') {
    require_once 'db_connect.php';
    $match_type_filter = $_GET['match_type'] ?? 'all';
    
    // キャッシュされたプレーヤー一覧を取得
    $cache_file = __DIR__ . '/data/cache_players.json';
    $players = [];
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
        // キャッシュファイルが単純な配列の場合と、['players']キーを持つ場合に対応
        if (isset($cache_data['players'])) {
            $players = $cache_data['players'];
        } elseif (is_array($cache_data) && !empty($cache_data) && is_string($cache_data[0])) {
            // 単純な文字列配列の場合
            foreach ($cache_data as $player_name) {
                $players[] = ['name' => $player_name];
            }
        }
    }
    
    $all_stats = [];
    
    foreach ($players as $player) {
        $player_name = $player['name'] ?? '';
        if (empty($player_name)) continue;
        
        $stats = [
            'name' => $player_name,
            'total_games' => 0,
            'wins' => 0,
            'final_scores' => [],
            'score_diffs' => [],
            'placements' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'total_rank_sum' => 0,
            'best_final_score' => 0,
            'max_consecutive_wins' => 0,
            'max_consecutive_renzai' => 0
        ];
        
        try {
            // データベースからプレイヤーのゲーム記録を取得
            $sql = "SELECT game_date, score, point, rank, game_type 
                    FROM results 
                    WHERE player_name = :player_name 
                    ORDER BY game_date";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':player_name' => $player_name]);
            $player_games = $stmt->fetchAll();
            
            foreach ($player_games as $game) {
                $game_type = $game['game_type'];
                
                if ($match_type_filter === 'official' && $game_type !== 'official') continue;
                if ($match_type_filter === 'unofficial' && $game_type === 'official') continue;
                
                $stats['final_scores'][] = (float)$game['point'];
                $stats['score_diffs'][] = (int)$game['score'];
                $stats['total_games']++;
                
                $position = (int)$game['rank'];
                $stats['placements'][$position]++;
                $stats['total_rank_sum'] += $position;
                
                if ($position === 1) {
                    $stats['wins']++;
                }
                
                if ((float)$game['point'] > $stats['best_final_score']) {
                    $stats['best_final_score'] = (float)$game['point'];
                }
            }
        } catch (PDOException $e) {
            error_log('Database error in player_api.php: ' . $e->getMessage());
            continue;
        }
        
        // 連対数を計算（ランク1と2の連続性）
        $max_renzai = 0;
        $current_renzai = 0;
        if ($stats['total_games'] > 0) {
            // ゲーム順序を時系列で取得
            try {
                $sql_renzai = "SELECT rank 
                              FROM results 
                              WHERE player_name = :player_name 
                              AND game_type " . ($match_type_filter === 'official' ? "= 'official'" : "IN ('official', 'unofficial')") . "
                              ORDER BY game_date ASC";
                $stmt_renzai = $pdo->prepare($sql_renzai);
                $stmt_renzai->execute([':player_name' => $player_name]);
                $renzai_games = $stmt_renzai->fetchAll();
                
                foreach (array_reverse($renzai_games) as $game) {
                    $pos = (int)$game['rank'];
                    if ($pos === 1 || $pos === 2) {
                        $current_renzai++;
                        $max_renzai = max($max_renzai, $current_renzai);
                    } else {
                        $current_renzai = 0;
                    }
                }
            } catch (PDOException $e) {
                error_log('Error calculating renzai: ' . $e->getMessage());
            }
        }
        $stats['max_consecutive_renzai'] = $max_renzai;
        
        // 計算用データ構築
        if ($stats['total_games'] > 0) {
            $all_stats[$player_name] = $stats;
        }
    }
    
    // 計算済み統計を追加
    $result = [];
    foreach ($all_stats as $player_name => $stats) {
        $result[$player_name] = [
            'total_games' => $stats['total_games'],
            'wins' => $stats['wins'],
            'win_rate' => round(($stats['wins'] / $stats['total_games']) * 100, 1),
            'avg_final_score' => round(array_sum($stats['final_scores']) / $stats['total_games'], 0),
            'avg_rank' => round($stats['total_rank_sum'] / $stats['total_games'], 2),
            'best_final_score' => $stats['best_final_score'],
            'top_rate' => round(($stats['placements'][1] / $stats['total_games']) * 100, 1),
            'last_avoidance_rate' => round((($stats['total_games'] - $stats['placements'][4]) / $stats['total_games']) * 100, 1),
            'total_score' => (int)round(array_sum($stats['score_diffs'])),
            'last_place_count' => $stats['placements'][4],
            'max_consecutive_renzai' => $stats['max_consecutive_renzai']
        ];
    }
    
    // ソート用のキーを取得
    $sort_key = $_GET['sort'] ?? 'total_games';
    $sort_order = $_GET['order'] ?? 'desc';
    
    uasort($result, function($a, $b) use ($sort_key, $sort_order) {
        $val_a = $a[$sort_key] ?? 0;
        $val_b = $b[$sort_key] ?? 0;
        
        if ($sort_order === 'asc') {
            return $val_a <=> $val_b;
        } else {
            return $val_b <=> $val_a;
        }
    });
    
    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => '不正なリクエストです'], JSON_UNESCAPED_UNICODE);
