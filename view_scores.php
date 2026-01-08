<?php
require_once 'includes/init.php';
$page_title = '成績閲覧';
$current_page = basename(__FILE__);

$score_file = __DIR__ . '/data/scores.csv';
$score_file = __DIR__ . '/data/scores.csv';
// manual refresh to ensure the latest file contents are used
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    clearstatcache(true, $score_file);
    if (function_exists('opcache_invalidate')) opcache_invalidate($score_file, true);
    header('Location: view_scores.php?refreshed=1'); exit;
}
clearstatcache(true);
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($score_file, true);
}
$players = [];
$game_history = [];
$total_point_sum = 0.0;
$filter = isset($_GET['filter']) && $_GET['filter'] === 'all' ? 'all' : 'official';

if (file_exists($score_file)) {
    $fp = fopen($score_file, 'r');
    while ($line = fgetcsv($fp)) {
        $match_type = $line[13] ?? 'unknown';
        $should_include_in_ranking = ($filter === 'all' || $match_type === 'official');
        $game_data = ['date' => $line[0], 'type' => $match_type, 'players' => []];
        for ($i = 0; $i < 4; $i++) {
            $name = $line[$i * 3 + 1];
            $score = (int)$line[$i * 3 + 2];
            $final_score = (float)$line[$i * 3 + 3];
            $rank = $i + 1;
            if ($should_include_in_ranking) {
                $total_point_sum += $final_score;
                if (!isset($players[$name])) {
                    $players[$name] = ['games' => 0, 'points' => 0.0, 'total_rank' => 0, 'ranks' => [0, 0, 0, 0], 'best_score' => PHP_INT_MIN];
                }
                $players[$name]['games']++;
                $players[$name]['points'] += $final_score;
                $players[$name]['total_rank'] += $rank;
                $players[$name]['ranks'][$i]++;
                if ($score > $players[$name]['best_score']) {
                    $players[$name]['best_score'] = $score;
                }
            }
            $game_data['players'][] = ['name' => $name, 'score' => $score, 'final_score' => $final_score, 'rank' => $rank];
        }
        $game_history[] = $game_data;
    }
    fclose($fp);
    if (!empty($players)) {
        uasort($players, function($a, $b) {
            return $b['points'] <=> $a['points'];
        });
    }
    rsort($game_history);
}

include 'includes/header.php';
?>
        <h1>成績閲覧</h1>
        <p class="catchphrase-small">「単位より重い牌」</p>

        <?php if (abs($total_point_sum) > 0.01): ?>
            <div class="section">
                <h2>データ整合性チェック</h2>
                <div class="zero-sum-check">
                    <p class="zero-sum-error">警告: 全スコア合計が <?php echo sprintf('%+.1f', $total_point_sum); ?> です。データに異常の可能性があります。</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="section">
            <?php if (isset($_GET['refreshed']) && $_GET['refreshed'] == '1'): ?><div class="status-message success">成績データを再読み込みしました。</div><?php endif; ?>
            <div style="text-align:center;margin-bottom:12px;"><a href="view_scores.php?refresh=1" class="btn">最新を取得</a></div>
            <div class="ranking-header">
                <h2>個人総合ランキング</h2>
                <div class="filter-nav">
                    <a href="?filter=official" class="btn-filter <?php if ($filter === 'official') echo 'active'; ?>">公式戦のみ</a>
                    <a href="?filter=all" class="btn-filter <?php if ($filter === 'all') echo 'active'; ?>">すべて含める</a>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>順位</th><th>名前</th><th>総合<br>ポイント</th><th>試合数</th><th>平均<br>順位</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($players)): ?>
                            <tr><td colspan="5">対象の対戦記録がありません。</td></tr>
                        <?php else: ?>
                            <?php $rank_counter = 1; foreach ($players as $name => $data): ?>
                                <tr>
                                    <td class="rank-<?php echo $rank_counter; ?>"><?php echo $rank_counter++; ?>位</td>
                                    <td><a href="player_profile.php?name=<?php echo urlencode($name); ?>" style="color:#667eea; text-decoration:none; cursor:pointer;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo htmlspecialchars($name); ?></a></td>
                                    <td class="<?php echo ($data['points'] >= 0) ? 'positive' : 'negative'; ?>"><?php echo sprintf('%+.1f', $data['points']); ?></td>
                                    <td><?php echo $data['games']; ?></td>
                                    <td><?php echo round($data['total_rank'] / $data['games'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <h2>対戦履歴</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>日時</th><th>試合種別</th><th>1位</th><th>2位</th><th>3位</th><th>4位</th></tr></thead>
                    <tbody>
                        <?php 
                        // フィルターを適用した対戦履歴を作成
                        $filtered_game_history = array_filter($game_history, function($game) use ($filter) {
                            return $filter === 'all' || $game['type'] === 'official';
                        });
                        ?>
                        <?php if (empty($filtered_game_history)): ?>
                            <tr><td colspan="6">対象の対戦記録がありません。</td></tr>
                        <?php else: ?>
                            <?php foreach ($filtered_game_history as $game): ?>
                                <tr style="cursor:pointer; transition:background-color 0.2s;" onclick="openMatchDetail('<?php echo htmlspecialchars($game['date']); ?>')" onmouseover="this.style.backgroundColor='#f5f5f5'" onmouseout="this.style.backgroundColor='white'">
                                    <td><?php echo htmlspecialchars(substr($game['date'], 0, 10)); ?></td>
                                    <td>
                                        <?php if ($game['type'] === 'official'): ?>
                                            <span class="tag-official">公式戦</span>
                                        <?php else: ?>
                                            <span class="tag-unofficial">非公式戦</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($game['players'] as $player): ?>
                                        <td>
                                            <a href="player_profile.php?name=<?php echo urlencode($player['name']); ?>" style="color:#667eea; text-decoration:none; cursor:pointer;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'" onclick="event.stopPropagation();"><?php echo htmlspecialchars($player['name']); ?></a><br>
                                            <small><?php echo number_format($player['score']); ?> (<?php echo sprintf('%+.1f', $player['final_score']); ?>)</small>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- 試合詳細モーダル -->
<div id="match-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; overflow-y:auto;">
    <div style="background:white; margin:20px auto; border-radius:12px; max-width:800px; max-height:90vh; overflow-y:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3); position:relative;">
        <button onclick="closeMatchDetail()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:28px; cursor:pointer; color:#999; transition:color 0.2s;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'">×</button>
        
        <div id="match-detail-content" style="padding:30px;">
            <div style="text-align:center; padding:40px;">
                <div style="font-size:24px; color:#999;">読み込み中...</div>
            </div>
        </div>
    </div>
</div>

<script>
function openMatchDetail(matchDate) {
    const modal = document.getElementById('match-modal');
    const content = document.getElementById('match-detail-content');
    
    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align:center; padding:40px;"><div style="font-size:24px; color:#999;">読み込み中...</div></div>';
    
    // スクロール位置を上に
    modal.scrollTop = 0;
    
    fetch(`match_detail_api.php?action=get_match&date=${encodeURIComponent(matchDate)}`)
        .then(res => res.json())
        .then(match => {
            displayMatchDetail(match);
        })
        .catch(err => {
            content.innerHTML = '<div style="padding:40px; text-align:center; color:#f44336;">エラーが発生しました</div>';
            console.error(err);
        });
}

function closeMatchDetail() {
    document.getElementById('match-modal').style.display = 'none';
}

function displayMatchDetail(match) {
    const content = document.getElementById('match-detail-content');
    let typeText = match.type === 'official' ? '公式戦' : '非公式戦';
    let typeColor = match.type === 'official' ? '#4caf50' : '#ff9800';
    
    let html = `
        <h2 style="margin-top:0; color:#333; border-bottom:3px solid #667eea; padding-bottom:15px;">
            試合詳細 - ${match.date.substring(0, 10)}
        </h2>
        
        <div style="background:#f5f5f5; padding:15px; border-radius:8px; margin-bottom:20px;">
            <span style="background:${typeColor}; color:white; padding:6px 12px; border-radius:4px; font-weight:bold;">${typeText}</span>
        </div>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin-bottom:30px;">
    `;
    
    match.players.forEach((player, idx) => {
        const rankColors = ['#f44336', '#ff9800', '#8bc34a', '#2196f3'];
        const rankLabels = ['1位', '2位', '3位', '4位'];
        
        html += `
            <div style="background:#fff; border:2px solid ${rankColors[idx]}; border-radius:8px; padding:15px; text-align:center;">
                <div style="background:${rankColors[idx]}; color:white; padding:8px; border-radius:4px; margin-bottom:10px; font-weight:bold; font-size:1.1em;">
                    ${rankLabels[idx]}
                </div>
                <a href="player_profile.php?name=${encodeURIComponent(player.name)}" style="color:#667eea; text-decoration:none; font-weight:bold; display:block; margin-bottom:8px; cursor:pointer;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                    ${escapeHtml(player.name)}
                </a>
                <div style="font-size:1.2em; font-weight:bold; color:#333; margin-bottom:5px;">
                    ${Number(player.score).toLocaleString()}点
                </div>
                <div style="color:#666; font-size:0.9em;">
                    ${player.final_score >= 0 ? '+' : ''}${player.final_score.toFixed(1)}
                </div>
            </div>
        `;
    });
    
    html += `
        </div>
        
        <hr style="border:none; border-top:2px solid #ddd; margin:30px 0;">
        
        <h3 style="margin-top:30px; color:#333;">コメント</h3>
        <div id="comments-section" style="max-height:400px; overflow-y:auto;">
            ${match.comments && match.comments.length > 0 ? '' : '<p style="color:#999; text-align:center; padding:20px;">コメントはまだありません</p>'}
        </div>
        
        <div style="background:#f5f5f5; padding:20px; border-radius:8px; margin-top:20px;">
            <h4 style="margin-top:0;">コメントを投稿</h4>
            <form id="comment-form" onsubmit="postComment(event, '${match.date}')">
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">選手名</label>
                    <select id="player-name" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;" required>
                        <option value="">選択してください</option>
    `;
    
    match.players.forEach(player => {
        html += `<option value="${escapeHtml(player.name)}">${escapeHtml(player.name)}</option>`;
    });
    
    html += `
                    </select>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">コメント</label>
                    <textarea id="comment-text" placeholder="試合の感想などをコメント..." style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; min-height:80px; font-family:inherit; font-size:1em;" required></textarea>
                </div>
                <button type="submit" style="background:#667eea; color:white; padding:12px 24px; border:none; border-radius:6px; font-weight:bold; cursor:pointer; width:100%; min-height:44px;">投稿</button>
            </form>
        </div>
    `;
    
    content.innerHTML = html;
    
    // コメントを表示
    if (match.comments && match.comments.length > 0) {
        const commentsSection = document.getElementById('comments-section');
        commentsSection.innerHTML = '';
        
        match.comments.forEach(comment => {
            const commentDiv = document.createElement('div');
            commentDiv.style.cssText = 'background:#fff; border:1px solid #ddd; padding:15px; border-radius:8px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:flex-start;';
            
            const commentContent = document.createElement('div');
            commentContent.style.cssText = 'flex:1;';
            commentContent.innerHTML = `
                <div style="font-weight:bold; color:#333; margin-bottom:5px;">${escapeHtml(comment.player_name)}</div>
                <div style="color:#666; font-size:0.9em; margin-bottom:8px;">${escapeHtml(comment.text)}</div>
                <div style="font-size:0.8em; color:#999;">${comment.timestamp}</div>
            `;
            
            const deleteBtn = document.createElement('button');
            deleteBtn.textContent = '削除';
            deleteBtn.style.cssText = 'background:#f44336; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:0.85em; margin-left:10px; white-space:nowrap;';
            deleteBtn.onclick = (e) => {
                e.preventDefault();
                if (confirm('このコメントを削除しますか？')) {
                    deleteComment('${match.date}', '${comment.id}');
                }
            };
            
            commentDiv.appendChild(commentContent);
            commentDiv.appendChild(deleteBtn);
            commentsSection.appendChild(commentDiv);
        });
    }
}

function postComment(event, matchDate) {
    event.preventDefault();
    
    const playerName = document.getElementById('player-name').value;
    const commentText = document.getElementById('comment-text').value;
    
    if (!playerName || !commentText) {
        alert('必須項目を入力してください');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'post_comment');
    formData.append('date', matchDate);
    formData.append('player_name', playerName);
    formData.append('comment', commentText);
    
    fetch('match_detail_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            // モーダルを再読み込み
            openMatchDetail(matchDate);
        } else {
            alert('コメントの投稿に失敗しました');
        }
    })
    .catch(err => {
        alert('エラーが発生しました');
        console.error(err);
    });
}

function deleteComment(matchDate, commentId) {
    const formData = new FormData();
    formData.append('action', 'delete_comment');
    formData.append('date', matchDate);
    formData.append('comment_id', commentId);
    
    fetch('match_detail_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            openMatchDetail(matchDate);
        } else {
            alert('コメントの削除に失敗しました');
        }
    })
    .catch(err => {
        alert('エラーが発生しました');
        console.error(err);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// モーダルの外側をクリックして閉じる
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('match-modal');
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>