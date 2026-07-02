<?php
require('dbconnect.php');
session_start();


if (isset($_SESSION['id'])){
    $id = $_REQUEST['id'] ?? 0;
    $member_id = $_SESSION['id'];


    // 1. 削除対象の投稿を取得（作成日を確認するため）
    $messages = $db->prepare('SELECT * FROM posts WHERE id=?');
    $messages->execute(array($id));
    $message = $messages->fetch();


    // 自分の投稿であることを確認
    if ($message && $message['member_id'] == $member_id){
       
        $post_date = date('Y-m-d', strtotime($message['created']));
        $today = date('Y-m-d');


        // 2. ランキングデータの更新
        if ($post_date === $today) {
            // 【今日】の投稿を消す場合：ポイント(-5) と 投稿件数(-1) を両方減らす
            $update_ranking = $db->prepare("
                UPDATE ranking
                SET points = points - 5,
                    post_count = post_count - 1
                WHERE member_id = ? AND last_post_date = ?
            ");
            $update_ranking->execute(array($member_id, $today));
        } else {
            // 【昨日以前】の投稿を消す場合：ポイント(-5) のみを減らす（件数は維持）
            $update_ranking = $db->prepare("
                UPDATE ranking
                SET points = points - 5
                WHERE member_id = ?
            ");
            $update_ranking->execute(array($member_id));
        }


        // 3. 投稿を削除
        $del = $db->prepare('DELETE FROM posts WHERE id=?');
        $del->execute(array($id));
    }
}


header('Location: index.php');
exit();
?>

