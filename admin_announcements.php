<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/init.php';

// 管理権限が無ければログインへ
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

$page_title = 'お知らせ管理';
$current_page = 'admin_announcements.php';

$announcements_file = __DIR__ . '/data/announcements.json';
if (!file_exists($announcements_file)) {
    file_put_contents($announcements_file, json_encode(['announcements' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

include 'includes/header.php';
?>
    <h1>お知らせ管理</h1>
    
    <div style="margin-bottom: 30px;">
        <button class="btn" onclick="showAddForm()">新規お知らせを追加</button>
    </div>

    <!-- 追加/編集フォーム -->
    <div id="announcement-form" style="display: none; background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 2px solid #ddd;">
        <h2 style="margin-top: 0;">お知らせを追加・編集</h2>
        <form id="form-announcement">
            <input type="hidden" id="form-id" value="">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">タイトル</label>
                <input type="text" id="form-title" placeholder="お知らせのタイトルを入力" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-family: inherit;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">内容</label>
                <textarea id="form-content" placeholder="お知らせの内容を入力" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; min-height: 120px; font-family: inherit;"></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn">保存</button>
                <button type="button" class="btn" style="background: #999; color: white;" onclick="hideAddForm()">キャンセル</button>
            </div>
        </form>
    </div>

    <!-- お知らせ一覧 -->
    <div id="announcements-list" style="display: flex; flex-direction: column; gap: 15px;">
        <p style="text-align: center; color: #999;">読み込み中...</p>
    </div>

    <script>
        // お知らせ一覧を読み込む
        function loadAnnouncements() {
            fetch('announcement_api.php?action=get_all')
                .then(res => res.json())
                .then(data => {
                    const listDiv = document.getElementById('announcements-list');
                    if (data.success && data.announcements.length > 0) {
                        listDiv.innerHTML = '';
                        data.announcements.forEach(ann => {
                            const annElement = document.createElement('div');
                            annElement.style.cssText = 'background: white; border: 1px solid #ddd; padding: 15px; border-radius: 8px;';
                            
                            const titleSmall = document.createElement('small');
                            titleSmall.style.color = '#999';
                            titleSmall.textContent = '更新: ' + (ann.updated_at || ann.created_at);
                            
                            const title = document.createElement('h3');
                            title.style.cssText = 'margin: 0 0 5px 0;';
                            title.textContent = ann.title;
                            
                            const titleDiv = document.createElement('div');
                            titleDiv.appendChild(title);
                            titleDiv.appendChild(titleSmall);
                            
                            const editBtn = document.createElement('button');
                            editBtn.className = 'btn';
                            editBtn.style.cssText = 'padding: 5px 10px; font-size: 0.9em;';
                            editBtn.textContent = '編集';
                            editBtn.onclick = () => editAnnouncement(ann.id, ann.title, ann.content);
                            
                            const deleteBtn = document.createElement('button');
                            deleteBtn.className = 'btn';
                            deleteBtn.style.cssText = 'background: #ff6b6b; color: white; padding: 5px 10px; font-size: 0.9em;';
                            deleteBtn.textContent = '削除';
                            deleteBtn.onclick = () => deleteAnnouncement(ann.id);
                            
                            const buttonsDiv = document.createElement('div');
                            buttonsDiv.style.cssText = 'display: flex; gap: 5px;';
                            buttonsDiv.appendChild(editBtn);
                            buttonsDiv.appendChild(deleteBtn);
                            
                            const headerDiv = document.createElement('div');
                            headerDiv.style.cssText = 'display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;';
                            headerDiv.appendChild(titleDiv);
                            headerDiv.appendChild(buttonsDiv);
                            
                            const content = document.createElement('p');
                            content.style.cssText = 'margin: 0; line-height: 1.6; color: #333; white-space: pre-wrap;';
                            content.textContent = ann.content;
                            
                            annElement.appendChild(headerDiv);
                            annElement.appendChild(content);
                            
                            listDiv.appendChild(annElement);
                        });
                    } else {
                        listDiv.innerHTML = '<p style="text-align: center; color: #999;">お知らせはまだありません</p>';
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    document.getElementById('announcements-list').innerHTML = '<p style="color: #c33; text-align: center;">読み込みに失敗しました</p>';
                });
        }

        // フォームを表示
        function showAddForm() {
            document.getElementById('form-id').value = '';
            document.getElementById('form-title').value = '';
            document.getElementById('form-content').value = '';
            document.getElementById('announcement-form').style.display = 'block';
            document.querySelector('h2', document.getElementById('announcement-form')).textContent = 'お知らせを追加';
            document.getElementById('form-title').focus();
        }

        // フォームを非表示
        function hideAddForm() {
            document.getElementById('announcement-form').style.display = 'none';
        }

        // お知らせを編集
        function editAnnouncement(id, title, content) {
            document.getElementById('form-id').value = id;
            document.getElementById('form-title').value = title;
            document.getElementById('form-content').value = content;
            document.getElementById('announcement-form').style.display = 'block';
            document.querySelector('#announcement-form > h2').textContent = 'お知らせを編集';
            document.getElementById('form-title').focus();
        }

        // フォーム送信
        document.getElementById('form-announcement').addEventListener('submit', function(e) {
            e.preventDefault();
            const id = document.getElementById('form-id').value;
            const title = document.getElementById('form-title').value;
            const content = document.getElementById('form-content').value;

            if (!title.trim() || !content.trim()) {
                alert('タイトルと内容を入力してください');
                return;
            }

            const formData = new FormData();
            formData.append('title', title);
            formData.append('content', content);

            let url = 'announcement_api.php?action=add';
            if (id) {
                formData.append('id', id);
                url = 'announcement_api.php?action=update';
            }

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    hideAddForm();
                    loadAnnouncements();
                } else {
                    alert('エラー: ' + data.error);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('エラーが発生しました');
            });
        });

        // お知らせを削除
        function deleteAnnouncement(id) {
            if (!confirm('このお知らせを削除しますか？')) return;

            const formData = new FormData();
            formData.append('id', id);

            fetch('announcement_api.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadAnnouncements();
                } else {
                    alert('エラー: ' + data.error);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('エラーが発生しました');
            });
        }

        // HTML特殊文字をエスケープ
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // 初回読み込み
        loadAnnouncements();
    </script>

<?php include 'includes/footer.php'; ?>
