<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php';

$tie_breaker_data = $_SESSION['tie_breaker_data'] ?? null;
unset($_SESSION['tie_breaker_data']);

$manual_ranks = $_POST['ranks'] ?? null;

if (!$tie_breaker_data || !$manual_ranks) {
    header('Location: home.php');
    exit('エラー: データが不足しています。');
}

// プレイヤー名で元のデータを検索できるようにマップを作成
// players は save_score.php から点数降順で受け取っている配列
$players = $tie_breaker_data['players'];
$players_map = [];
$total_players = count($players);

// 名前でプレイヤーデータを保持（まだ rank は割り当てない）
foreach ($players as $i => $player_obj) {
    $players_map[$player_obj['name']] = $player_obj;
}

// 手動で設定された順位を適用（入力検証付き）
$assigned_ranks = [];
foreach ($manual_ranks as $name => $rank_val) {
    $rank = (int)$rank_val;
    if (!isset($players_map[$name])) continue; // 無効な名前は無視
    // 範囲チェック
    if ($rank < 1 || $rank > $total_players) {
        $_SESSION['tie_error'] = '無効な順位が選択されました。';
        // tie_breaker.php にデータを戻して再入力を促す
        $_SESSION['tie_breaker_data'] = $tie_breaker_data;
        header('Location: tie_breaker.php');
        exit();
    }
    $players_map[$name]['rank'] = $rank;
    $assigned_ranks[] = $rank;
}

// 重複チェック
if (count($assigned_ranks) !== count(array_unique($assigned_ranks))) {
    $_SESSION['tie_error'] = '同じ順位が複数選択されています。再入力してください。';
    $_SESSION['tie_breaker_data'] = $tie_breaker_data;
    header('Location: tie_breaker.php');
    exit();
}

// マニュアルで順位を選択していないプレイヤーには、残りの順位を元の順序に従って割り当てる
$used_ranks = [];
foreach ($assigned_ranks as $r) $used_ranks[$r] = true;
$free_ranks = [];
for ($r = 1; $r <= $total_players; $r++) {
    if (empty($used_ranks[$r])) $free_ranks[] = $r;
}

// 点数降順で並んでいる $players の順に、未割当プレイヤーへ空き順位を割り当てる
foreach ($players as $p) {
    $name = $p['name'];
    if (isset($players_map[$name]['rank'])) continue; // 既に手動で割り当て済み
    $players_map[$name]['rank'] = array_shift($free_ranks);
}

// 最終的なプレイヤーリストを作成し、rankでソート
$final_ordered_players = array_values($players_map);
usort($final_ordered_players, function($a, $b) {
    return $a['rank'] <=> $b['rank'];
});

// ルール設定
$start_point = 25000;
$return_point = 30000;
$uma = [50, 10, -10, -30];
$match_date = $tie_breaker_data['match_date'];
$match_type = $tie_breaker_data['match_type'];
$save_file = __DIR__ . '/data/scores.csv';

// 最終順位に基づいたスコア計算
$results = [];
foreach ($final_ordered_players as $index => $player) {
    $actual_score = $player['score'] * 100;
    $raw_score_adj = ($actual_score - $return_point) / 1000;
    $final_score = $raw_score_adj + $uma[$index];

    $results[] = [
        'name' => $player['name'],
        'score' => $actual_score,
        'rank' => $player['rank'], // 手動で設定したランクを使用
        'final_score' => $final_score
    ];
}

// ファイル書き込み
$line_to_write = [$match_date];
foreach ($results as $player) {
    $line_to_write[] = $player['name'];
    $line_to_write[] = $player['score'];
    $line_to_write[] = $player['final_score'];
}
$line_to_write[] = $match_type;

// 安全に追記: 末尾に改行がなければ挿入してから fputcsv で書く
$fp = fopen($save_file, 'c+');
if ($fp !== false) {
    if (flock($fp, LOCK_EX)) {
        fseek($fp, 0, SEEK_END);
        $stat = fstat($fp);
        if ($stat['size'] > 0) {
            fseek($fp, -1, SEEK_END);
            $last = fread($fp, 1);
            if ($last !== "\n" && $last !== "\r") {
                fwrite($fp, PHP_EOL);
            }
        }
        fputcsv($fp, $line_to_write);
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        $prefix = '';
        if (file_exists($save_file) && filesize($save_file) > 0) {
            $fh = fopen($save_file, 'rb');
            if ($fh) {
                fseek($fh, -1, SEEK_END);
                $last = fread($fh, 1);
                fclose($fh);
                if ($last !== "\n" && $last !== "\r") $prefix = PHP_EOL;
            }
        }
        file_put_contents($save_file, $prefix . implode(',', $line_to_write), FILE_APPEND | LOCK_EX);
    }
    fclose($fp);
} else {
    $prefix = '';
    if (file_exists($save_file) && filesize($save_file) > 0) {
        $fh = fopen($save_file, 'rb');
        if ($fh) {
            fseek($fh, -1, SEEK_END);
            $last = fread($fh, 1);
            fclose($fh);
            if ($last !== "\n" && $last !== "\r") $prefix = PHP_EOL;
        }
    }
    file_put_contents($save_file, $prefix . implode(',', $line_to_write), FILE_APPEND | LOCK_EX);
}

// 完了画面にデータを渡してリダイレクト
$_SESSION['last_recorded_results'] = [
    'results' => $results,
    'is_official' => ($match_type === 'official')
];
header('Location: score_completion.php');
exit();
?>