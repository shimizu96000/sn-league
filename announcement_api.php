<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 管理権限確認
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '権限がありません']);
    exit;
}

$announcements_file = __DIR__ . '/data/announcements.json';

// ファイルが無ければ初期化
if (!file_exists($announcements_file)) {
    file_put_contents($announcements_file, json_encode(['announcements' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

try {
    $announcements_data = json_decode(file_get_contents($announcements_file), true) ?? ['announcements' => []];
    
    switch ($action) {
        case 'get_all':
            // すべてのお知らせを取得（最新順）
            $announcements = $announcements_data['announcements'] ?? [];
            usort($announcements, function($a, $b) {
                return strtotime($b['updated_at'] ?? $b['created_at']) - strtotime($a['updated_at'] ?? $a['created_at']);
            });
            echo json_encode(['success' => true, 'announcements' => $announcements]);
            break;

        case 'add':
            // 新規お知らせを追加
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'POST only']);
                exit;
            }

            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';

            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'タイトルと内容は必須です']);
                exit;
            }

            $id = uniqid();
            $now = date('Y-m-d H:i:s');
            $new_announcement = [
                'id' => $id,
                'title' => $title,
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $announcements_data['announcements'][] = $new_announcement;
            file_put_contents($announcements_file, json_encode($announcements_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            echo json_encode(['success' => true, 'id' => $id, 'message' => 'お知らせを追加しました']);
            break;

        case 'update':
            // お知らせを更新
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'POST only']);
                exit;
            }

            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';

            if (empty($id) || empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'ID、タイトル、内容は必須です']);
                exit;
            }

            $found = false;
            $announcements = $announcements_data['announcements'] ?? [];
            foreach ($announcements as &$announcement) {
                if ($announcement['id'] === $id) {
                    $announcement['title'] = $title;
                    $announcement['content'] = $content;
                    $announcement['updated_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                echo json_encode(['success' => false, 'error' => 'お知らせが見つかりません']);
                exit;
            }

            $announcements_data['announcements'] = $announcements;
            file_put_contents($announcements_file, json_encode($announcements_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            echo json_encode(['success' => true, 'message' => 'お知らせを更新しました']);
            break;

        case 'delete':
            // お知らせを削除
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'POST only']);
                exit;
            }

            $id = $_POST['id'] ?? '';

            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'IDは必須です']);
                exit;
            }

            $announcements = $announcements_data['announcements'] ?? [];
            $filtered = array_filter($announcements, function($a) use ($id) {
                return $a['id'] !== $id;
            });

            if (count($filtered) === count($announcements)) {
                echo json_encode(['success' => false, 'error' => 'お知らせが見つかりません']);
                exit;
            }

            $announcements_data['announcements'] = array_values($filtered);
            file_put_contents($announcements_file, json_encode($announcements_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            echo json_encode(['success' => true, 'message' => 'お知らせを削除しました']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
