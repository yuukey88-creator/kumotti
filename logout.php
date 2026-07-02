<?php
session_start();

// 1. 現在のIDをリストから削除する
if (isset($_SESSION['id']) && isset($_SESSION['account_list'])) {
    // リストの中から現在のIDの場所（キー）を探す
    $key = array_search($_SESSION['id'], $_SESSION['account_list']);
   
    // 見つかったら削除する
    if ($key !== false) {
        unset($_SESSION['account_list'][$key]);
        // 削除して空いたインデックスを詰める
        $_SESSION['account_list'] = array_values($_SESSION['account_list']);
    }
}

// 2. Cookie情報を削除（自動ログイン解除）
setcookie('email', '', time() - 60*60*24*14);
setcookie('password', '', time() - 60*60*24*14);

// 3. 次に誰にログインするか決める
if (!empty($_SESSION['account_list'])) {
    // まだ他の人が残っていれば、リストの最初の人を「今のログイン者」にする
    $_SESSION['id'] = $_SESSION['account_list'][0];
    $_SESSION['time'] = time();
    $next_page = 'index.php';
} else {
    // 誰もいなくなったら、セッションを空にしてログイン画面へ
    $_SESSION = array();
    $next_page = 'login.php';
}

header('Location: ' . $next_page);
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログアウト</title>
</head>
<body>

</body>
</html>

