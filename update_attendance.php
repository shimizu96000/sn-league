<?php
$app_script_url = 'https://script.google.com/macros/s/AKfycbyCFgtZziO3ziHlmTpF2a3MhaiHR4VLs0-IJ_5EmZPLYJTuR9lrExg9thVc--UUntaW/exec';

$dates = $_POST['dates'] ?? null; // カンマ区切りの複数日
$player = $_POST['player'] ?? null;
$status = $_POST['status'] ?? null;
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n');

// dates を解釈して複数送信
$sent_any = false;
$updated_items = [];
if ($dates && $player && $status) {
    $date_list = array_filter(array_map('trim', explode(',', $dates)));
    foreach ($date_list as $d) {
        // 入力は 'Y-n-j' 形式を期待
        // タイムゾーンずれ対策: JST 12:00 の ISO 文字列を渡す
        $date_iso = null;
        $dt = DateTime::createFromFormat('Y-n-j', $d, new DateTimeZone('Asia/Tokyo'));
        if ($dt) {
            $dt->setTime(12, 0, 0);
            $date_iso = $dt->format('c'); // ISO 8601
        } else {
            // フォールバック: try strtotime
            $ts = strtotime($d);
            if ($ts !== false) {
                $dt = new DateTime('@' . $ts);
                $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));
                $dt->setTime(12,0,0);
                $date_iso = $dt->format('c');
            }
        }
        $payload = json_encode(['date' => $d, 'date_iso' => $date_iso, 'player' => $player, 'status' => $status]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $app_script_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $output = curl_exec($ch);
        curl_close($ch);
        $output_data = json_decode($output, true);
        if ($output_data && isset($output_data['result']) && $output_data['result'] === 'success') {
            $sent_any = true;
            $updated_items[] = ['date' => $d, 'player' => $player, 'status' => $status];
            } else {
            // フォールバック: Apps Script が form-encoded を期待する場合に備えて再送
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $app_script_url);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch2, CURLOPT_POST, true);
            $form = http_build_query(['date' => $d, 'date_iso' => $date_iso, 'player' => $player, 'status' => $status]);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $form);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 8);
            $output2 = curl_exec($ch2);
            curl_close($ch2);
            $output_data2 = json_decode($output2, true);
            if ($output_data2 && isset($output_data2['result']) && $output_data2['result'] === 'success') {
                $sent_any = true;
                $updated_items[] = ['date' => $d, 'player' => $player, 'status' => $status];
            }
        }
    }
    if ($sent_any) {
        $cache_file = __DIR__ . '/data/cache_attendance.json';
        if (file_exists($cache_file)) unlink($cache_file);
    }
}

// fetch からの非同期要求には JSON を返す
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (stripos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $sent_any, 'updated' => $updated_items]);
    exit();
}

// 通常フォーム送信の場合はリダイレクト
header("Location: calendar.php?year={$year}&month={$month}&view=attendance");
exit();
?>