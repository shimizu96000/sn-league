<?php
// player_api.php - 選手情報のAPI処理

header('Content-Type: application/json; charset=utf-8');

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
    $save_file = __DIR__ . '/data/scores.csv';
    $match_type_filter = $_GET['match_type'] ?? 'all';
    
    if (!file_exists($save_file)) {
        echo json_encode(['success' => false, 'error' => 'スコアファイルが見つかりません'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
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
        
        $fp = fopen($save_file, 'r');
        if ($fp === false) continue;
        
        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) < 14) continue;
            
            $match_type = $row[count($row)-1] ?? '';
            
            if ($match_type_filter === 'official' && $match_type !== 'official') continue;
            if ($match_type_filter === 'unofficial' && $match_type === 'official') continue;
            
            $names_in_game = [
                trim($row[1] ?? ''),
                trim($row[4] ?? ''),
                trim($row[7] ?? ''),
                trim($row[10] ?? '')
            ];
            
            $scores_in_game = [
                floatval($row[3] ?? 0),
                floatval($row[6] ?? 0),
                floatval($row[9] ?? 0),
                floatval($row[12] ?? 0)
            ];
            
            $final_scores_in_game = [
                floatval($row[2] ?? 0),
                floatval($row[5] ?? 0),
                floatval($row[8] ?? 0),
                floatval($row[11] ?? 0)
            ];
            
            $player_idx = array_search($player_name, $names_in_game);
            
            if ($player_idx !== false) {
                $stats['final_scores'][] = $final_scores_in_game[$player_idx];
                $stats['score_diffs'][] = $scores_in_game[$player_idx];
                $stats['total_games']++;
                
                $rankings = array_keys($scores_in_game);
                usort($rankings, function($a, $b) use ($scores_in_game) {
                    return $scores_in_game[$b] <=> $scores_in_game[$a];
                });
                $position = array_search($player_idx, $rankings) + 1;
                $stats['placements'][$position]++;
                $stats['total_rank_sum'] += $position;
                
                if ($position === 1) {
                    $stats['wins']++;
                }
                
                if ($final_scores_in_game[$player_idx] > $stats['best_final_score']) {
                    $stats['best_final_score'] = $final_scores_in_game[$player_idx];
                }
            }
        }
        fclose($fp);
        
        // 最大連対数を計算
        $max_renzai = 0;
        $current_renzai = 0;
        if ($stats['total_games'] > 0) {
            $fp = fopen($save_file, 'r');
            if ($fp !== false) {
                $temp_games = [];
                while (($row = fgetcsv($fp)) !== false) {
                    if (count($row) < 14) continue;
                    
                    $match_type = $row[count($row)-1] ?? '';
                    if ($match_type_filter === 'official' && $match_type !== 'official') continue;
                    if ($match_type_filter === 'unofficial' && $match_type === 'official') continue;
                    
                    $names_in_game = [
                        trim($row[1] ?? ''),
                        trim($row[4] ?? ''),
                        trim($row[7] ?? ''),
                        trim($row[10] ?? '')
                    ];
                    
                    $scores_in_game = [
                        floatval($row[3] ?? 0),
                        floatval($row[6] ?? 0),
                        floatval($row[9] ?? 0),
                        floatval($row[12] ?? 0)
                    ];
                    
                    $player_idx = array_search($player_name, $names_in_game);
                    if ($player_idx !== false) {
                        $rankings = array_keys($scores_in_game);
                        usort($rankings, function($a, $b) use ($scores_in_game) {
                            return $scores_in_game[$b] <=> $scores_in_game[$a];
                        });
                        $position = array_search($player_idx, $rankings) + 1;
                        $temp_games[] = $position;
                    }
                }
                fclose($fp);
                
                foreach (array_reverse($temp_games) as $position) {
                    if ($position === 1 || $position === 2) {
                        $current_renzai++;
                        $max_renzai = max($max_renzai, $current_renzai);
                    } else {
                        $current_renzai = 0;
                    }
                }
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
