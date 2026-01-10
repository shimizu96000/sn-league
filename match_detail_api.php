<?php
require_once 'includes/init.php';
require_once 'db_connect.php';

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
    
    try {
        $sql = "SELECT player_name, score, point, rank, game_type 
                FROM results 
                WHERE game_date = :date 
                ORDER BY rank";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date' => $match_date]);
        $players = $stmt->fetchAll();
        
        if (empty($players)) {
            http_response_code(404);
            echo json_encode(['error' => '試合が見つかりません']);
            exit;
        }
        
        $match_data = [
            'date' => $match_date,
            'type' => $players[0]['game_type'],
            'players' => []
        ];
        
        foreach ($players as $player) {
            $match_data['players'][] = [
                'name' => $player['player_name'],
                'score' => (int)$player['score'],
                'final_score' => (float)$player['point'],
                'rank' => (int)$player['rank']
            ];
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
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
