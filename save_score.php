<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php';

// --- ルール設定 ---
$start_point = 25000;
$return_point = 30000;
$uma = [50, 10, -10, -30];
$save_file = __DIR__ . '/data/scores.csv';

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

// 共通の試合情報
$match_date = date("Y-m-d H:i:s");
$current_player_names = array_column($players, 'name');
sort($current_player_names);
$current_combination_id = implode('-', $current_player_names);

$is_official = true;
if (file_exists($save_file)) {
    $fp_read = fopen($save_file, 'r');
    while ($line_read = fgetcsv($fp_read)) {
        if (isset($line_read[13]) && $line_read[13] === 'official') {
            $past_player_names = [$line_read[1], $line_read[4], $line_read[7], $line_read[10]];
            sort($past_player_names);
            $past_combination_id = implode('-', $past_player_names);
            if ($current_combination_id === $past_combination_id) {
                $is_official = false;
                break;
            }
        }
    }
    fclose($fp_read);
}
$match_type = $is_official ? 'official' : 'unofficial';


// 同点者がいれば、tie_breaker.phpへリダイレクト
if ($has_tie) {
    $_SESSION['tie_breaker_data'] = [
        'players' => $players, // 点数でソート済みのプレイヤーデータ
        'match_date' => $match_date,
        'match_type' => $match_type
    ];
    header('Location: tie_breaker.php');
    exit();
}

// 同点者がいない場合の処理
$results = [];
foreach ($players as $index => $player) {
    $actual_score = $player['score'] * 100;
    $raw_score_adj = ($actual_score - $return_point) / 1000;
    $final_score = $raw_score_adj + $uma[$index];
    
    $results[] = [
        'name' => $player['name'],
        'score' => $actual_score,
        'rank' => $index + 1,
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
$fp = fopen($save_file, 'c+'); // c+ allows read/write without truncation
if ($fp !== false) {
    if (flock($fp, LOCK_EX)) {
        // move to end
        fseek($fp, 0, SEEK_END);
        $stat = fstat($fp);
        if ($stat['size'] > 0) {
            // check last byte
            fseek($fp, -1, SEEK_END);
            $last = fread($fp, 1);
            if ($last !== "\n" && $last !== "\r") {
                fwrite($fp, PHP_EOL);
            }
        }
        // write CSV line
        fputcsv($fp, $line_to_write);
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        // ロック失敗時フォールバック: ファイル末尾を確認して改行を付けてから追記
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
    // fopen 失敗時のフォールバック
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
    'is_official' => $is_official
];
header('Location: score_completion.php');
exit();
?>