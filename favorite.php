<?php
session_start();
require('dbconnect.php');

// ログインチェック
if (isset($_SESSION['id'])) {
    $post_id = $_REQUEST['id'];
    $member_id = $_SESSION['id'];

    if ($post_id > 0) {
        $fav = $db->prepare('
            INSERT INTO favorites 
                SET member_id=?, post_id=?, count=1, updated=NOW()
            ON DUPLICATE KEY UPDATE 
                count = count + 1, updated=NOW()
        ');
        $fav->execute(array($member_id, $post_id));
    }
}

// 処理が終わったら、すぐに index.php に戻す
header('Location: index.php');
exit();