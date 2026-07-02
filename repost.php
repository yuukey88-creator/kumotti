<?php
session_start();
require('dbconnect.php');
if (isset($_SESSION['id'])) {
    $post_id = $_REQUEST['id'];
    $member_id = $_SESSION['id'];
    if ($post_id > 0) {
        // すでにリツイートしていないかチェック（二重登録防止）
        $check = $db->prepare('SELECT COUNT(*) AS cnt FROM reposts WHERE member_id=? AND post_id=?');
        $check->execute(array($member_id, $post_id));
        $res = $check->fetch();
        if ($res['cnt'] == 0) {
            // リツイートを登録
            $retweet = $db->prepare('INSERT INTO reposts SET member_id=?, post_id=?, created=NOW()');
            $retweet->execute(array($member_id, $post_id));


            // 2. ★投稿者のIDを取得する
            $stmt = $db->prepare('SELECT member_id FROM posts WHERE id=?');
            $stmt->execute(array($post_id));
            $post_owner = $stmt->fetch();


            // 3. ★投稿者にポイントを2加算
            // 投稿者と今のユーザーが違う場合のみ加算
            if ($post_owner && $post_owner['member_id'] != $member_id) {
                $update = $db->prepare('UPDATE ranking SET points = points + 2 WHERE member_id=?');
                $update->execute(array($post_owner['member_id']));
            }
        }
    }
}
header('Location: index.php');
exit();
?>