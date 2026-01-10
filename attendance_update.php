<?php
// attendance_update.php
// 簡易的な出欠更新エンドポイント（ダミー実装）
// 期待する POST JSON: { playerId: string|int, date: 'YYYY-MM-DD', status: '参加'|'不参加'|'未定' }

header('Content-Type: application/json; charset=utf-8');
// Allow CORS for local testing (ローカル環境でのみ利用推奨)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight
    http_response_code(200);
    echo json_encode(["success" => true]);
    exit;
}

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No input"]);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid JSON"]);
    exit;
}

$playerId = isset($data['playerId']) ? $data['playerId'] : null;
$date = isset($data['date']) ? $data['date'] : null;
$status = isset($data['status']) ? $data['status'] : null;

// 簡易バリデーション
if (!$playerId || !$date || !$status) {
    http_response_code(422);
    echo json_encode(["success" => false, "error" => "Missing fields"]);
    exit;
}

// TODO: 本来はここで DB に保存する。現在はダミーで成功を返す
// もし持続的保存が必要なら、SQLite や MySQL 接続を実装してください。

// ログに出力（XAMPP の Apache error log に出る）
error_log("Attendance update: player=$playerId date=$date status=$status\n");

http_response_code(200);
echo json_encode(["success" => true, "playerId" => $playerId, "date" => $date, "status" => $status]);
exit;
?>