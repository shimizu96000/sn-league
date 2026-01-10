<?php
// POSTメソッドで送信された'score'という名前のデータを受け取る
$received_score = $_POST['score'];

// 受け取った数値を画面に表示する
echo "<h1>" . htmlspecialchars($received_score) . " を受け取りました！</h1>";

// リンクで入力画面に戻れるようにする
echo "<a href='input.html'>入力画面に戻る</a>";
?>