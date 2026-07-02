<?php
session_start();
require('dbconnect.php');


if (isset($_SESSION['id'])) {
    $post_id = $_REQUEST['id'];
    $member_id = $_SESSION['id'];


    if ($post_id > 0) {
        // 1. 実際にリツイートしていたか確認（ポイントを減らす前の前提条件）
        $check = $db->prepare('SELECT COUNT(*) FROM reposts WHERE member_id=? AND post_id=?');
        $check->execute(array($member_id, $post_id));
       
        if ($check->fetchColumn() > 0) {
            // 2. リツイート記録を削除
            $del = $db->prepare('DELETE FROM reposts WHERE member_id=? AND post_id=?');
            $del->execute(array($member_id, $post_id));


            // 3. 投稿者のIDを取得
            $stmt = $db->prepare('SELECT member_id FROM posts WHERE id=?');
            $stmt->execute(array($post_id));
            $post_owner = $stmt->fetch();


            // 4. ポイントを2減らす（投稿者と本人が異なる場合）
            if ($post_owner && $post_owner['member_id'] != $member_id) {
                $update = $db->prepare('UPDATE ranking SET points = points - 2 WHERE member_id=?');
                $update->execute(array($post_owner['member_id']));
            }
        }
    }
}


header('Location: index.php');
exit();
?>