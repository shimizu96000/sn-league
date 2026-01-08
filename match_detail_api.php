<?php
require_once 'includes/init.php';

header('Content-Type: application/json; charset=utf-8');

// キャッシュヘッダー
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 試合詳細の取得
if ($action === 'get_match') {
    $match_date = $_GET['date'] ?? '';
    
    if (empty($match_date)) {
        http_response_code(400);
        echo json_encode(['error' => '日付が指定されていません']);
        exit;
    }
    
    $score_file = __DIR__ . '/data/scores.csv';
    $match_data = null;
    
    if (file_exists($score_file)) {
        $fp = fopen($score_file, 'r');
        while ($line = fgetcsv($fp)) {
            if ($line[0] === $match_date) {
                $match_type = $line[13] ?? 'unknown';
                $match_data = [
                    'date' => $line[0],
                    'type' => $match_type,
                    'players' => []
                ];
                
                for ($i = 0; $i < 4; $i++) {
                    $name = $line[$i * 3 + 1];
                    $score = (int)$line[$i * 3 + 2];
                    $final_score = (float)$line[$i * 3 + 3];
                    $rank = $i + 1;
                    
                    $match_data['players'][] = [
                        'name' => $name,
                        'score' => $score,
                        'final_score' => $final_score,
                        'rank' => $rank
                    ];
                }
                break;
            }
        }
        fclose($fp);
    }
    
    if (!$match_data) {
        http_response_code(404);
        echo json_encode(['error' => '試合が見つかりません']);
        exit;
    }
    
    // コメントの読み込み
    $comments_file = __DIR__ . '/match_comments.json';
    $comments = [];
    
    if (file_exists($comments_file)) {
        $all_comments = json_decode(file_get_contents($comments_file), true) ?? [];
        $comments = $all_comments[$match_date] ?? [];
    }
    
    // コメントを日時順でソート
    usort($comments, function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
    });
    
    $match_data['comments'] = $comments;
    
    echo json_encode($match_data);
    exit;
}

// コメントの投稿
if ($action === 'post_comment') {
    $match_date = $_POST['date'] ?? '';
    $player_name = $_POST['player_name'] ?? '';
    $comment_text = $_POST['comment'] ?? '';
    
    if (empty($match_date) || empty($player_name) || empty($comment_text)) {
        http_response_code(400);
        echo json_encode(['error' => '必須項目が不足しています']);
        exit;
    }
    
    $comments_file = __DIR__ . '/match_comments.json';
    $all_comments = [];
    
    if (file_exists($comments_file)) {
        $all_comments = json_decode(file_get_contents($comments_file), true) ?? [];
    }
    
    if (!isset($all_comments[$match_date])) {
        $all_comments[$match_date] = [];
    }
    
    $new_comment = [
        'id' => uniqid(),
        'player_name' => $player_name,
        'text' => $comment_text,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $all_comments[$match_date][] = $new_comment;
    
    file_put_contents($comments_file, json_encode($all_comments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'comment' => $new_comment]);
    exit;
}

// コメントの削除
if ($action === 'delete_comment') {
    $match_date = $_POST['date'] ?? '';
    $comment_id = $_POST['comment_id'] ?? '';
    
    if (empty($match_date) || empty($comment_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'パラメータが不足しています']);
        exit;
    }
    
    $comments_file = __DIR__ . '/match_comments.json';
    
    if (!file_exists($comments_file)) {
        http_response_code(404);
        echo json_encode(['error' => 'コメントが見つかりません']);
        exit;
    }
    
    $all_comments = json_decode(file_get_contents($comments_file), true) ?? [];
    
    if (!isset($all_comments[$match_date])) {
        http_response_code(404);
        echo json_encode(['error' => 'この試合のコメントが見つかりません']);
        exit;
    }
    
    $all_comments[$match_date] = array_filter($all_comments[$match_date], function($c) use ($comment_id) {
        return $c['id'] !== $comment_id;
    });
    
    file_put_contents($comments_file, json_encode($all_comments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => '不正なアクションです']);
?>
