<?php
require_once 'includes/init.php';
$page_title = '称号管理';
$current_page = basename(__FILE__);

// 称号データの読み込み
$titles_file = __DIR__ . '/data/player_titles.json';
$titles_data = [];

if (file_exists($titles_file)) {
    $titles_data = json_decode(file_get_contents($titles_file), true) ?? [];
}

// デフォルト構造の設定
if (empty($titles_data)) {
    $titles_data = ['titles' => [], 'custom_titles' => [], 'manual_titles' => []];
}

// 参加者リストを取得
$cache_file = __DIR__ . '/data/cache_players.json';
$participants = [];
if (file_exists($cache_file)) {
    $cached_data = json_decode(file_get_contents($cache_file), true);
    if (is_array($cached_data) && !empty($cached_data)) {
        $participants = $cached_data;
    }
}
sort($participants);

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_title') {
            $new_title = [
                'id' => uniqid(),
                'name' => $_POST['name'] ?? '',
                'icon' => $_POST['icon'] ?? '🏆',
                'condition' => $_POST['condition'] ?? '',
                'description' => $_POST['description'] ?? ''
            ];
            
            if (!empty($new_title['name']) && !empty($new_title['condition'])) {
                $titles_data['custom_titles'][$new_title['id']] = $new_title;
                file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $success_message = '称号を追加しました。';
            } else {
                $error_message = '名前と条件は必須です。';
            }
        } elseif ($_POST['action'] === 'delete_title') {
            $title_id = $_POST['title_id'] ?? '';
            $title_type = $_POST['title_type'] ?? 'custom';
            
            if ($title_type === 'default' && isset($titles_data['titles'])) {
                // デフォルト称号を配列から削除
                $titles_data['titles'] = array_filter($titles_data['titles'], function($t) use ($title_id) {
                    return $t['id'] !== $title_id;
                });
                $titles_data['titles'] = array_values($titles_data['titles']);
                file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $success_message = 'デフォルト称号を削除しました。';
            } elseif ($title_type === 'custom' && isset($titles_data['custom_titles'][$title_id])) {
                unset($titles_data['custom_titles'][$title_id]);
                file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $success_message = 'カスタム称号を削除しました。';
            }
        } elseif ($_POST['action'] === 'edit_title') {
            $title_id = $_POST['title_id'] ?? '';
            $title_type = $_POST['title_type'] ?? 'custom';
            
            $updated_title = [
                'id' => $title_id,
                'name' => $_POST['name'] ?? '',
                'icon' => $_POST['icon'] ?? '🏆',
                'condition' => $_POST['condition'] ?? '',
                'description' => $_POST['description'] ?? ''
            ];
            
            if (!empty($updated_title['name']) && !empty($updated_title['condition'])) {
                if ($title_type === 'default') {
                    // デフォルト称号を更新
                    $found = false;
                    foreach ($titles_data['titles'] as &$t) {
                        if ($t['id'] === $title_id) {
                            $t = $updated_title;
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        $success_message = 'デフォルト称号を更新しました。';
                    }
                } elseif ($title_type === 'custom' && isset($titles_data['custom_titles'][$title_id])) {
                    $titles_data['custom_titles'][$title_id] = $updated_title;
                    file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $success_message = 'カスタム称号を更新しました。';
                }
            } else {
                $error_message = '名前と条件は必須です。';
            }
        } elseif ($_POST['action'] === 'add_manual_title') {
            // 特定の選手に無条件で称号を付与
            $player_name = $_POST['player_name'] ?? '';
            $title_id = $_POST['title_id'] ?? '';
            
            if (!empty($player_name) && !empty($title_id)) {
                // 選手のキーが存在しなければ作成
                if (!isset($titles_data['manual_titles'][$player_name])) {
                    $titles_data['manual_titles'][$player_name] = [];
                }
                
                // 同じIDが既に存在しないかチェック
                if (!in_array($title_id, $titles_data['manual_titles'][$player_name])) {
                    $titles_data['manual_titles'][$player_name][] = $title_id;
                    file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $success_message = '称号を付与しました。';
                } else {
                    $error_message = 'その選手は既にこの称号を持っています。';
                }
            } else {
                $error_message = '選手名と称号を選択してください。';
            }
        } elseif ($_POST['action'] === 'remove_manual_title') {
            // 選手から直接付与された称号を削除
            $player_name = $_POST['player_name'] ?? '';
            $title_id = $_POST['title_id'] ?? '';
            
            if (!empty($player_name) && !empty($title_id) && isset($titles_data['manual_titles'][$player_name])) {
                $titles_data['manual_titles'][$player_name] = array_filter(
                    $titles_data['manual_titles'][$player_name],
                    function($id) use ($title_id) { return $id !== $title_id; }
                );
                
                // 空の配列は削除
                if (empty($titles_data['manual_titles'][$player_name])) {
                    unset($titles_data['manual_titles'][$player_name]);
                }
                
                file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $success_message = '称号を削除しました。';
            }
        } elseif ($_POST['action'] === 'create_and_assign_title') {
            // 新しい個人付与称号を作成して付与
            $player_name = $_POST['player_name'] ?? '';
            $title_name = $_POST['title_name'] ?? '';
            $title_icon = $_POST['title_icon'] ?? '🏆';
            $title_desc = $_POST['title_description'] ?? '';
            
            if (!empty($player_name) && !empty($title_name)) {
                // 個人付与称号用にユニークなIDを生成
                $unique_id = 'personal_' . uniqid();
                
                // 個人付与用の称号オブジェクトを作成
                $new_personal_title = [
                    'id' => $unique_id,
                    'name' => $title_name,
                    'icon' => $title_icon,
                    'condition' => 'false', // 条件評価では付与されない
                    'description' => $title_desc,
                    'type' => 'personal' // マーカー
                ];
                
                // custom_titlesに追加
                $titles_data['custom_titles'][$unique_id] = $new_personal_title;
                
                // 選手に付与
                if (!isset($titles_data['manual_titles'][$player_name])) {
                    $titles_data['manual_titles'][$player_name] = [];
                }
                $titles_data['manual_titles'][$player_name][] = $unique_id;
                
                file_put_contents($titles_file, json_encode($titles_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $success_message = '新しい称号を作成して付与しました。';
            } else {
                $error_message = '選手名と称号名は必須です。';
            }
        }
    }
}

include 'includes/header.php';
?>

<h1>称号管理</h1>
<p>選手に付与する称号を管理します。</p>

<?php if (isset($success_message)): ?>
    <div style="background:#e8f5e9; border:2px solid #4caf50; color:#2e7d32; padding:15px; border-radius:8px; margin-bottom:20px;">
        ✓ <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div style="background:#ffebee; border:2px solid #f44336; color:#c62828; padding:15px; border-radius:8px; margin-bottom:20px;">
        ✗ <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- デフォルト称号 -->
<div class="section">
    <h2>デフォルト称号</h2>
    <p style="color:#666;">システムに組み込まれた自動付与される称号です。編集・削除も可能です。</p>
    
    <div style="display:grid; gap:15px;">
        <?php foreach (($titles_data['titles'] ?? []) as $index => $title): ?>
            <div style="background:#f5f5f5; padding:15px; border-radius:8px; border-left:4px solid #667eea;">
                <div style="display:flex; justify-content:space-between; align-items:start; gap:10px;">
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                            <span style="font-size:2em;"><?php echo $title['icon']; ?></span>
                            <div style="flex:1;">
                                <div style="font-weight:bold; font-size:1.1em;"><?php echo htmlspecialchars($title['name']); ?></div>
                                <div style="font-size:0.85em; color:#666;"><?php echo htmlspecialchars($title['description']); ?></div>
                            </div>
                        </div>
                        <div style="background:#fff; padding:10px; border-radius:4px; font-family:monospace; font-size:0.9em; color:#333; word-break:break-all;">
                            条件: <?php echo htmlspecialchars($title['condition']); ?>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button onclick="editTitle('<?php echo htmlspecialchars($title['id']); ?>', 'default')" class="btn" style="background:#ff9800; color:white; padding:8px 12px; border:none; border-radius:4px; cursor:pointer; font-size:0.9em;">編集</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('このデフォルト称号を削除してもよろしいですか？');">
                            <input type="hidden" name="action" value="delete_title">
                            <input type="hidden" name="title_id" value="<?php echo htmlspecialchars($title['id']); ?>">
                            <input type="hidden" name="title_type" value="default">
                            <button type="submit" class="btn" style="background:#f44336; color:white; padding:8px 12px; border:none; border-radius:4px; cursor:pointer; font-size:0.9em;">削除</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- カスタム称号 -->
<div class="section">
    <h2>カスタム称号</h2>
    <p style="color:#666;">独自に追加した称号を管理します。</p>
    
    <!-- 新規追加フォーム -->
    <div style="background:#e3f2fd; padding:20px; border-radius:12px; margin-bottom:25px; border:2px solid #2196f3;">
        <h3 style="margin-top:0;">新しい称号を追加</h3>
        <form method="POST" style="display:grid; gap:15px;">
            <input type="hidden" name="action" value="add_title">
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">称号名 <span style="color:red;">*</span></label>
                <input type="text" name="name" placeholder="例: チャンピオン" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" required>
            </div>
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">アイコン</label>
                <input type="text" name="icon" placeholder="例: 🏆" maxlength="2" style="width:100px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:1.5em;">
            </div>
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">付与条件 <span style="color:red;">*</span></label>
                <textarea name="condition" placeholder="例: total_games >= 20 AND avg_rank <= 2" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; min-height:60px; font-family:monospace; font-size:0.9em;" required></textarea>
                <small style="color:#666; display:block; margin-top:5px;">
                    利用可能な変数: total_games, wins, win_rate, avg_final_score, best_final_score, avg_rank, top_rate, last_avoidance_rate, total_score, last_place_count, max_consecutive_renzai
                </small>
            </div>
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">説明</label>
                <input type="text" name="description" placeholder="例: 20試合以上出場かつ平均順位2位以上" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            </div>
            
            <button type="submit" class="btn" style="background:#2196f3; color:white; font-weight:bold; padding:12px 20px; border:none; border-radius:6px; cursor:pointer; min-height:44px;">追加</button>
        </form>
    </div>
    
    <!-- 既存カスタム称号一覧 -->
    <?php if (!empty($titles_data['custom_titles'])): ?>
        <div style="display:grid; gap:15px;">
            <?php foreach ($titles_data['custom_titles'] as $id => $title): ?>
                <div style="background:#fff; padding:15px; border-radius:8px; border:2px solid #ddd;">
                    <div style="display:flex; justify-content:space-between; align-items:start; gap:10px;">
                        <div style="flex:1;">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                                <span style="font-size:2em;"><?php echo $title['icon']; ?></span>
                                <div style="flex:1;">
                                    <div style="font-weight:bold; font-size:1.1em;"><?php echo htmlspecialchars($title['name']); ?></div>
                                    <div style="font-size:0.85em; color:#666;"><?php echo htmlspecialchars($title['description']); ?></div>
                                </div>
                            </div>
                            <div style="background:#f5f5f5; padding:10px; border-radius:4px; font-family:monospace; font-size:0.9em; color:#333; word-break:break-all;">
                                条件: <?php echo htmlspecialchars($title['condition']); ?>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button onclick="editTitle('<?php echo htmlspecialchars($id); ?>', 'custom')" class="btn" style="background:#ff9800; color:white; padding:8px 12px; border:none; border-radius:4px; cursor:pointer; font-size:0.9em;">編集</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('この称号を削除してもよろしいですか？');">
                                <input type="hidden" name="action" value="delete_title">
                                <input type="hidden" name="title_id" value="<?php echo htmlspecialchars($id); ?>">
                                <input type="hidden" name="title_type" value="custom">
                                <button type="submit" class="btn" style="background:#f44336; color:white; padding:8px 12px; border:none; border-radius:4px; cursor:pointer; font-size:0.9em;">削除</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color:#999; text-align:center; padding:30px;">カスタム称号はまだありません</p>
    <?php endif; ?>
</div>

<!-- 個人付与 -->
<div class="section">
    <h2>個人付与</h2>
    <p style="color:#666;">特定の選手に直接称号を付与します。（無条件）</p>
    
    <!-- 新規作成・付与フォーム -->
    <div style="background:#e8f5e9; padding:20px; border-radius:12px; margin-bottom:25px; border:2px solid #4caf50;">
        <h3 style="margin-top:0;">新しい称号を作成して付与</h3>
        <form method="POST" style="display:grid; gap:15px;">
            <input type="hidden" name="action" value="create_and_assign_title">
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">選手名 <span style="color:red;">*</span></label>
                <select name="player_name" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" required>
                    <option value="">選択してください</option>
                    <?php foreach ($participants as $player): ?>
                        <option value="<?php echo htmlspecialchars($player); ?>"><?php echo htmlspecialchars($player); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">称号名 <span style="color:red;">*</span></label>
                <input type="text" name="title_name" placeholder="例: チャンピオン" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" required>
            </div>
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">アイコン</label>
                <input type="text" name="title_icon" placeholder="例: 🏆" maxlength="2" value="🏆" style="width:100px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:1.5em;">
            </div>
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">説明</label>
                <input type="text" name="title_description" placeholder="例: 特別な成績を残した選手" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            </div>
            
            <button type="submit" class="btn" style="background:#4caf50; color:white; font-weight:bold; padding:12px 20px; border:none; border-radius:6px; cursor:pointer; min-height:44px;">作成して付与</button>
        </form>
    </div>
    
    <!-- 既存称号付与フォーム -->
    <div style="background:#fff3e0; padding:20px; border-radius:12px; margin-bottom:25px; border:2px solid #ff9800;">
        <h3 style="margin-top:0;">既存の称号を付与</h3>
        <form method="POST" style="display:grid; gap:15px;">
            <input type="hidden" name="action" value="add_manual_title">
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">選手名 <span style="color:red;">*</span></label>
                <select name="player_name" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" required>
                    <option value="">選択してください</option>
                    <?php foreach ($participants as $player): ?>
                        <option value="<?php echo htmlspecialchars($player); ?>"><?php echo htmlspecialchars($player); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">称号 <span style="color:red;">*</span></label>
                <select name="title_id" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" required>
                    <option value="">選択してください</option>
                    <optgroup label="デフォルト称号">
                        <?php foreach ($titles_data['titles'] ?? [] as $title): ?>
                            <option value="<?php echo htmlspecialchars($title['id']); ?>"><?php echo htmlspecialchars($title['icon'] . ' ' . $title['name']); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="カスタム称号">
                        <?php foreach ($titles_data['custom_titles'] ?? [] as $title): 
                            // 個人付与称号は除外
                            if (!isset($title['type']) || $title['type'] !== 'personal'): ?>
                                <option value="<?php echo htmlspecialchars($title['id']); ?>"><?php echo htmlspecialchars($title['icon'] . ' ' . $title['name']); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            
            <button type="submit" class="btn" style="background:#ff9800; color:white; font-weight:bold; padding:12px 20px; border:none; border-radius:6px; cursor:pointer; min-height:44px;">付与</button>
        </form>
    </div>
    
    <!-- 付与済み称号一覧 -->
    <?php if (!empty($titles_data['manual_titles'])): ?>
        <div style="display:grid; gap:15px;">
            <?php foreach ($titles_data['manual_titles'] as $player_name => $title_ids): ?>
                <div style="background:#fff; padding:15px; border-radius:8px; border:2px solid #ddd;">
                    <div style="font-weight:bold; font-size:1.1em; margin-bottom:12px;"><?php echo htmlspecialchars($player_name); ?></div>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <?php foreach ($title_ids as $title_id): ?>
                            <?php 
                                // 称号情報を検索
                                $title_info = null;
                                foreach (array_merge($titles_data['titles'] ?? [], array_values($titles_data['custom_titles'] ?? [])) as $t) {
                                    if ($t['id'] === $title_id) {
                                        $title_info = $t;
                                        break;
                                    }
                                }
                            ?>
                            <?php if ($title_info): ?>
                                <div style="background:#f5f5f5; padding:8px 12px; border-radius:6px; display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:1.2em;"><?php echo $title_info['icon']; ?></span>
                                    <span style="font-weight:bold;"><?php echo htmlspecialchars($title_info['name']); ?></span>
                                    <form method="POST" style="display:inline; margin:0; padding:0;">
                                        <input type="hidden" name="action" value="remove_manual_title">
                                        <input type="hidden" name="player_name" value="<?php echo htmlspecialchars($player_name); ?>">
                                        <input type="hidden" name="title_id" value="<?php echo htmlspecialchars($title_id); ?>">
                                        <button type="submit" style="background:none; border:none; color:#f44336; cursor:pointer; font-weight:bold; padding:0; margin:0 0 0 8px;" onclick="return confirm('この称号を削除しますか？');">✕</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color:#999; text-align:center; padding:30px;">付与済み称号はありません</p>
    <?php endif; ?>
</div>
</div>

<script>
function editTitle(titleId, titleType) {
    // 既存の編集フォームがあれば削除
    const existingForm = document.getElementById('edit-form-' + titleId);
    if (existingForm) {
        existingForm.remove();
        return;
    }
    
    // 編集対象の称号データを探す
    let titleData = null;
    const defaultTitles = <?php echo json_encode($titles_data['titles'] ?? []); ?>;
    const customTitles = <?php echo json_encode((object)($titles_data['custom_titles'] ?? [])); ?>;
    
    // デフォルト称号から検索
    for (let t of defaultTitles) {
        if (t.id === titleId) {
            titleData = t;
            break;
        }
    }
    
    // カスタム称号から検索
    if (!titleData && customTitles) {
        titleData = customTitles[titleId];
    }
    
    if (!titleData) {
        alert('称号が見つかりません');
        return;
    }
    
    // フォームを動的に生成
    const form = document.createElement('form');
    form.id = 'edit-form-' + titleId;
    form.method = 'POST';
    form.style.cssText = 'background:#fff3e0; padding:20px; border-radius:12px; border:2px solid #ff9800; margin:15px 0; display:grid; gap:15px;';
    
    form.innerHTML = `
        <h3 style="margin-top:0; color:#ff9800;">称号を編集</h3>
        <input type="hidden" name="action" value="edit_title">
        <input type="hidden" name="title_id" value="${titleId}">
        <input type="hidden" name="title_type" value="${titleType}">
        
        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">称号名</label>
            <input type="text" name="name" value="${titleData.name}" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" required>
        </div>
        
        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">アイコン</label>
            <input type="text" name="icon" value="${titleData.icon}" maxlength="2" style="width:100px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:1.5em;">
        </div>
        
        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">付与条件</label>
            <textarea name="condition" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; min-height:60px; font-family:monospace; font-size:0.9em;" required>${titleData.condition}</textarea>
        </div>
        
        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">説明</label>
            <input type="text" name="description" value="${titleData.description}" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
        </div>
        
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn" style="background:#ff9800; color:white; font-weight:bold; padding:12px 20px; border:none; border-radius:6px; cursor:pointer; min-height:44px;">更新</button>
            <button type="button" class="btn" style="background:#ccc; color:#333; font-weight:bold; padding:12px 20px; border:none; border-radius:6px; cursor:pointer; min-height:44px;">キャンセル</button>
        </div>
    `;
    
    // キャンセルボタンにイベントリスナーを追加
    const cancelBtn = form.querySelector('button[type="button"]');
    cancelBtn.addEventListener('click', function() {
        form.remove();
    });
    
    // 編集対象の要素を探して、その直後に挿入
    const container = document.body;
    if (titleType === 'default') {
        const defaultSection = document.querySelector('.section');
        if (defaultSection) {
            defaultSection.appendChild(form);
        }
    } else {
        const customSection = document.querySelectorAll('.section')[1];
        if (customSection) {
            customSection.appendChild(form);
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
