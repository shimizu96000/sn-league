<?php
// すべてのPHPファイルのinit.phpパスを修正するスクリプト
$dir = __DIR__;
$files = glob("$dir/*.php");

foreach ($files as $file) {
    $content = file_get_contents($file);
    $newContent = preg_replace("/require_once\s+['\"]init\.php['\"]/", "require_once 'includes/init.php'", $content);
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "修正: " . basename($file) . "\n";
    }
}

echo "完了\n";
?>
