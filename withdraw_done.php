<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
    $id = $_SESSION['id'];

    // 1. DBからデータを削除
    $delPost = $db->prepare('DELETE FROM posts WHERE member_id=?');
    $delPost->execute(array($id));

    $delMember = $db->prepare('DELETE FROM members WHERE id=?');
    $delMember->execute(array($id));

    // 2. 【修正】アカウントリストから自分を削除する
    if (isset($_SESSION['account_list'])) {
        $key = array_search($id, $_SESSION['account_list']);
        if ($key !== false) {
            unset($_SESSION['account_list'][$key]);
            $_SESSION['account_list'] = array_values($_SESSION['account_list']);
        }
    }

    // 3. 【修正】次に誰に切り替えるかを判定する
    if (!empty($_SESSION['account_list'])) {
        // まだ他の人が残っていれば、その人のIDをセット
        $_SESSION['id'] = $_SESSION['account_list'][0];
        $_SESSION['time'] = time();
        $next_page = 'index.php'; // タイムラインへ
    } else {
        // 誰もいなくなったら、完全にログアウト
        $_SESSION = array();
        session_destroy();
        $next_page = 'login.php'; // ログイン画面へ
    }
}

header('Location: ' . $next_page);
exit();
?>