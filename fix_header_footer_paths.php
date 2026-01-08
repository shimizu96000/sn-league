<?php
// すべてのPHPファイルのheader.phpとfooter.phpのパスを修正するスクリプト
$dir = __DIR__;
$files = glob("$dir/*.php");

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $newContent = $content;
    
    // header.phpの修正
    $newContent = preg_replace("/include\s+['\"]header\.php['\"]/", "include 'includes/header.php'", $newContent);
    
    // footer.phpの修正
    $newContent = preg_replace("/include\s+['\"]footer\.php['\"]/", "include 'includes/footer.php'", $newContent);
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "修正: " . basename($file) . "\n";
        $count++;
    }
}

echo "合計 " . $count . " ファイルを修正しました\n";
?>
