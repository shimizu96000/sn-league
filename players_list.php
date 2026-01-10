<?php
require_once 'includes/init.php';
$page_title = '選手一覧';
$current_page = basename(__FILE__);

// 参加者リストを取得
$participants_csv_url = 'https://script.google.com/macros/s/AKfycbyCFgtZziO3ziHlmTpF2a3MhaiHR4VLs0-IJ_5EmZPLYJTuR9lrExg9thVc--UUntaW/exec';

function curl_get_contents($url) { 
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $output = curl_exec($ch); 
    $error = curl_error($ch);
    curl_close($ch); 
    if ($error) error_log('CURL Error: ' . $error);
    return $output; 
}

// キャッシュから参加者を読む
$cache_file = __DIR__ . '/data/cache_players.json';
$participants = [];

if (file_exists($cache_file)) {
    $cached_data = json_decode(file_get_contents($cache_file), true);
    if (is_array($cached_data) && !empty($cached_data)) {
        $participants = $cached_data;
    }
}

// キャッシュが無い、または参加者が少ない場合はAPIから取得
if (count($participants) < 8) {
    $json_data = curl_get_contents($participants_csv_url);
    if ($json_data) {
        $names_array = json_decode($json_data, true);
        if (is_array($names_array)) {
            $participants = [];
            foreach ($names_array as $name_with_prefix) { 
                $name = preg_replace('/^\d+\./', '', trim($name_with_prefix)); 
                if (!empty($name)) $participants[] = $name; 
            }
            file_put_contents($cache_file, json_encode($participants, JSON_UNESCAPED_UNICODE));
        }
    }
}

sort($participants);
$participants = array_slice($participants, 0, 8);

// 画像情報を読み込む
$player_images_file = __DIR__ . '/data/player_images.json';
$player_images = [];
if (file_exists($player_images_file)) {
    $player_images = json_decode(file_get_contents($player_images_file), true) ?? [];
}
$images_dir = __DIR__ . '/player_images';

include 'includes/header.php';
?>
    <div style="background:#ffffff; border-bottom:3px solid #667eea; padding:30px 0; margin-bottom:35px;">
        <h1 style="margin:0; font-size:42px; font-weight:bold; color:#333;">選手一覧</h1>
        <p style="margin:10px 0 0 0; font-size:16px; color:#999;">各選手のプロフィール・成績を確認できます</p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom:40px;">
        <?php foreach ($participants as $player): ?>
            <a href="player_profile.php?name=<?php echo urlencode($player); ?>" style="display: flex; flex-direction: column; padding: 0; background: white; color: #333; text-decoration: none; border-radius: 12px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); cursor: pointer; overflow: hidden; border: 2px solid transparent;" onmouseover="this.style.boxShadow='0 8px 24px rgba(102, 126, 234, 0.3)'; this.style.transform='translateY(-4px)';" onmouseout="this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.1)'; this.style.transform='translateY(0)';">
                <!-- 画像 -->
                <div style="width: 100%; padding-bottom: 100%; position: relative; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <?php if (isset($player_images[$player]) && file_exists($images_dir . '/' . $player_images[$player])): ?>
                        <img src="player_images/<?php echo htmlspecialchars($player_images[$player]); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; display: block;">
                    <?php else: ?>
                        <div style="text-align: center; color: white; font-size: 3em;">🎯</div>
                    <?php endif; ?>
                </div>
                <!-- テキスト -->
                <div style="padding: 20px;">
                    <div style="font-size: 18px; line-height: 1.4; margin-bottom: 12px;"><?php echo htmlspecialchars($player); ?></div>
                    <div style="font-size: 12px; opacity: 0.7; padding-top: 12px; border-top: 1px solid #e0e0e0;">プロフィールを表示 →</div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

<?php include 'includes/footer.php'; ?>
