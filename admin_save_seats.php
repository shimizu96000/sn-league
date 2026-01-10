<?php
require_once 'includes/init.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_seats.php'); exit;
}
// posted structure: seats[match_index][player] = seat
$posted = $_POST['seats'] ?? [];
$file = __DIR__ . '/data/combination_seats.json';
$existing = [];
if (file_exists($file)) {
    $existing = json_decode(file_get_contents($file), true) ?: [];
}

// load matches to know participants by index
$cache_file = __DIR__ . '/data/cache_matches.csv';
$matches = [];
if (file_exists($cache_file)) {
    $csv = file_get_contents($cache_file);
    $csv = mb_convert_encoding($csv, 'UTF-8', 'auto');
    $lines = explode("\n", $csv);
    foreach ($lines as $i => $line) {
        if ($i === 0 || trim($line) === '') continue;
        $row = str_getcsv($line);
        if (empty($row[0])) continue;
        $dt = DateTime::createFromFormat('Y/n/j G:i~', $row[0], new DateTimeZone('Asia/Tokyo'));
        if ($dt === false) continue;
        $players = array_filter([trim($row[1] ?? ''), trim($row[2] ?? ''), trim($row[3] ?? ''), trim($row[4] ?? '')]);
        if (count($players) === 0) continue;
        $matches[] = $players;
    }
}

foreach ($posted as $match_idx => $map) {
    if (!isset($matches[$match_idx])) continue;
    $players = $matches[$match_idx];
    // build sorted key as used elsewhere
    $sorted = $players;
    sort($sorted);
    $combo_key = implode('|', $sorted);
    // create seat->player mapping from posted player->seat map
    $seat_map = [];
    foreach ($map as $player => $seat) {
        $p = trim($player);
        $s = trim($seat);
        if ($p === '' || $s === '') continue;
        $seat_map[$s] = $p;
    }
    if (!empty($seat_map)) {
        $existing[$combo_key] = $seat_map;
    }
}

// safe write
$tmp = tempnam(sys_get_temp_dir(), 'seats');
file_put_contents($tmp, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
rename($tmp, $file);

header('Location: admin_seats.php?saved=1');
exit;
