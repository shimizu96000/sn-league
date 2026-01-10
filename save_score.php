<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php';
require_once 'db_connect.php';

// 権限チェック：成績入力は選手のみ
check_permission('submit_scores');

// --- ルール設定 ---
$start_point = 25000;
$return_point = 30000;
$uma = [50, 10, -10, -30];

// --- プログラム処理 ---

$players_input = $_POST['players'];
$players = [];
foreach ($players_input as $p) {
    $players[] = ['name' => $p['name'], 'score' => (int)$p['score']];
}

// 点数で降順ソート
usort($players, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

// 同点グループを検出
$scores = array_column($players, 'score');
$score_counts = array_count_values($scores);
$has_tie = false;
foreach ($score_counts as $count) {
    if ($count > 1) {
        $has_tie = true;
        break;
    }
}

// 同点者がいれば、tie_breaker.phpへリダイレクト
if ($has_tie) {
    $current_player_names = array_column($players, 'name');
    sort($current_player_names);
    $current_combination_id = implode('-', $current_player_names);
    
    $_SESSION['tie_breaker_data'] = [
        'players' => $players,
        'combination_id' => $current_combination_id
    ];
    header('Location: tie_breaker.php');
    exit();
}

// 同点者がいない場合の処理
$results = [];
foreach ($players as $index => $player) {
    $actual_score = $player['score'] * 100;  // 入力値を100倍（364 -> 36400）
    $raw_score_adj = ($actual_score - $return_point) / 1000;
    $final_score = $raw_score_adj + $uma[$index];
    
    $results[] = [
        'name' => $player['name'],
        'score' => $actual_score,
        'rank' => $index + 1,
        'final_score' => $final_score
    ];
}

// 公式戦判定：同じプレイヤー組み合わせが過去にあるか確認
$is_official = true;
$current_player_names = array_column($players, 'name');
sort($current_player_names);
$current_combination_id = implode('-', $current_player_names);

try {
    $sql = "SELECT DISTINCT player_name FROM results 
            WHERE player_name IN (?, ?, ?, ?) 
            GROUP BY game_date 
            HAVING COUNT(DISTINCT player_name) = 4
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($current_player_names);
    
    if ($stmt->rowCount() > 0) {
        // 同じ組み合わせが過去に存在する場合は非公式
        $is_official = false;
    }
} catch (PDOException $e) {
    error_log('Database error checking official status: ' . $e->getMessage());
    // エラー時は非公式扱い（保守的）
    $is_official = false;
}

// --- データベースへの保存処理 ---
try {
    $sql = "INSERT INTO results (game_date, player_name, score, point, rank, game_type) 
            VALUES (:date, :name, :score, :point, :rank, :game_type)";
    $stmt = $pdo->prepare($sql);

    // 現在時刻
    $now = date('Y-m-d H:i:s');

    // 4人分繰り返して保存
    foreach ($results as $player) {
        $stmt->execute([
            ':date' => $now,
            ':name' => $player['name'],
            ':score' => $player['score'],
            ':point' => $player['final_score'],
            ':rank' => $player['rank'],
            ':game_type' => $is_official ? 'official' : 'unofficial'
        ]);
    }

} catch (PDOException $e) {
    error_log('Database error saving score: ' . $e->getMessage());
    $_SESSION['error'] = 'スコア保存中にエラーが発生しました: ' . $e->getMessage();
    header('Location: score_form.php');
    exit();
}

// 完了画面にデータを渡してリダイレクト
$_SESSION['last_recorded_results'] = [
    'results' => $results,
    'is_official' => $is_official
];
header('Location: score_completion.php');
exit();
?>