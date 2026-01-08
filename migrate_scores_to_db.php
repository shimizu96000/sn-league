<?php
/**
 * scores.csvからresultsテーブルへのデータ移行スクリプト
 * このスクリプトを実行するとCSVデータがデータベースに移行されます
 */

require_once 'db_connect.php';

$scores_file = __DIR__ . '/data/scores.csv';

if (!file_exists($scores_file)) {
    die('scores.csvが見つかりません');
}

try {
    // 既存データをクリア（トランザクション外で実行）
    $pdo->exec("TRUNCATE TABLE results");
    
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO results (game_date, player_name, score, point, rank, game_type) 
            VALUES (:game_date, :player_name, :score, :point, :rank, :game_type)";
    $stmt = $pdo->prepare($sql);
    
    $insert_count = 0;
    $official_count = 0;
    $unofficial_count = 0;
    
    if (($handle = fopen($scores_file, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle)) !== FALSE) {
            // CSVフォーマット: [日時, 名前1, 素点1, 最終スコア1, 名前2, 素点2, 最終スコア2, ... , 種別]
            if (count($data) >= 13) {
                $game_date = trim($data[0], '"');
                $game_type = trim($data[13], '"');
                
                // 4人のプレイヤーデータを抽出（順位は固定：1位, 2位, 3位, 4位）
                for ($i = 0; $i < 4; $i++) {
                    $name_index = 1 + ($i * 3);
                    $score_index = 2 + ($i * 3);
                    $point_index = 3 + ($i * 3);
                    
                    if (isset($data[$name_index], $data[$score_index], $data[$point_index])) {
                        $player_name = trim($data[$name_index], '"');
                        $score = (int)trim($data[$score_index], '"');
                        $point = (float)trim($data[$point_index], '"');
                        $rank = $i + 1;
                        
                        $stmt->execute([
                            ':game_date' => $game_date,
                            ':player_name' => $player_name,
                            ':score' => $score,
                            ':point' => $point,
                            ':rank' => $rank,
                            ':game_type' => $game_type
                        ]);
                        
                        $insert_count++;
                        if ($game_type === 'official') {
                            $official_count++;
                        } else {
                            $unofficial_count++;
                        }
                    }
                }
            }
        }
        fclose($handle);
    }
    
    $pdo->commit();
    echo "✅ 移行完了\n";
    echo "   総レコード数: {$insert_count}件\n";
    echo "   公式戦: {$official_count}件\n";
    echo "   非公式戦: {$unofficial_count}件\n";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ エラー: " . $e->getMessage() . "\n";
}
?>


