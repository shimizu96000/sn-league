<?php
$cache = __DIR__ . '/data/cache_matches.csv';
$seatfile = __DIR__ . '/data/combination_seats.json';
$csv = file_get_contents($cache);
$csv = mb_convert_encoding($csv, 'UTF-8', 'auto');
$lines = explode("\n", $csv);
$comb = [];
if (file_exists($seatfile)) $comb = json_decode(file_get_contents($seatfile), true) ?: [];
$out = '';
foreach ($lines as $i => $line) {
    if ($i === 0 || trim($line) === '') continue;
    $row = str_getcsv($line);
    if (empty($row[0])) continue;
    $players = array_filter([trim($row[1] ?? ''), trim($row[2] ?? ''), trim($row[3] ?? ''), trim($row[4] ?? '')]);
    if (count($players) === 0) continue;
    $sorted = $players;
    $s2 = $sorted;
    sort($s2);
    $key = implode('|', $s2);
    $out .= "Match #$i key=$key\n";
    $assigned = [];
    $seats = ['東','南','西','北'];
    if (!empty($comb[$key])) {
        foreach ($comb[$key] as $seat_label => $player_name) $assigned[$player_name] = $seat_label;
        $out .= " persistent mapping found:\n";
        foreach ($assigned as $p => $s) { $out .= "  [$p] => $s\n"; }
    } else {
        $seed = $row[0] ?? implode(' ', [$s2[0] ?? '', $s2[1] ?? '']);
        $shuffled = $players;
        $seed_num = crc32($seed);
        mt_srand($seed_num);
        for ($j = count($shuffled) - 1; $j > 0; $j--) {
            $k = mt_rand(0, $j);
            $tmp = $shuffled[$j]; $shuffled[$j] = $shuffled[$k]; $shuffled[$k] = $tmp;
        }
        foreach ($shuffled as $idx2 => $pname) { if (isset($seats[$idx2])) $assigned[$pname] = $seats[$idx2]; }
        $out .= " generated mapping:\n";
        foreach ($assigned as $p => $s) { $out .= "  [$p] => $s\n"; }
    }
    $out .= " players list: ";
    foreach ($players as $p) $out .= "$p, ";
    $out .= "\n\n";
}

$out .= "done\n";
$log = __DIR__ . '/debug_mappings_output.txt';
file_put_contents($log, $out);

echo "Wrote to $log\n";
