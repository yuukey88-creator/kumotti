<?php
session_start();
require('dbconnect.php');


$post_id = $_POST['id'];
$member_id = $_SESSION['id'];


// 1. 重複を気にせず、押された分だけ保存する
// (もしDBでエラーが出る場合は、member_idを記録しないか、ユニーク制約を解除してください)
$ins = $db->prepare('INSERT INTO favorites SET member_id=?, post_id=?, count=1');
$ins->execute(array($member_id, $post_id));


// 2. 最新の合計件数を取得して返す
$stmt = $db->prepare('SELECT SUM(count) AS fav_count FROM favorites WHERE post_id=?');
$stmt->execute(array($post_id));
$fav = $stmt->fetch();


echo $fav['fav_count'] ?? 0;
exit();
?>