<?php
session_start();

// URLの「?id=数字」から切り替え先のIDを受け取る
$next_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

// 【データの道理】ログイン済みリストにそのIDが本当にあるか確認
if ($next_id > 0 && isset($_SESSION['account_list']) && in_array($next_id, $_SESSION['account_list'])) {
   
    // 現在のメインIDを切り替える
    $_SESSION['id'] = $next_id;
   
    // ログイン有効期限を現在時刻に更新（これをしないと期限切れで弾かれるため）
    $_SESSION['time'] = time();
}

// 処理が終わったらタイムライン（index.php）へ戻る
header('Location: index.php');
exit();
?>