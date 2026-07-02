<?php
session_start();
require('dbconnect.php');

$error = [];

// 1. 【表示用】クッキーがあり、かつ「まだ何も送信されていない」時だけ値をセット
if (empty($_POST) && isset($_COOKIE['email']) && $_COOKIE['email'] !='') {
    $email = $_COOKIE['email'];
    $password = $_COOKIE['password'];
    $save = 'on';
} else {
    // 送信された後、またはクッキーがない時
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $save = $_POST['save'] ?? '';
}

// 2. ログイン処理（実際にボタンが押された時だけ動く）
if (!empty($_POST)) {
    if ($email != '' && $password != '') {
        $login = $db->prepare('SELECT * FROM members WHERE email=? AND password=?');
        $login->execute(array(
            $email,
            sha1($password)
        ));
        $member = $login->fetch();

        if ($member) {
            // ログイン成功
            $_SESSION['id'] = $member['id'];
            $_SESSION['time'] = time();

            // アカウントリストへの追加
            if (!isset($_SESSION['account_list'])) {
                $_SESSION['account_list'] = array();
            }
            if (!in_array($member['id'], $_SESSION['account_list'])) {
                $_SESSION['account_list'][] = $member['id'];
            }

            // クッキーの保存（チェックが入っている時だけ）
            if ($save == 'on') {
                setcookie('email', $email, time() + 60 * 60 * 24 * 14);
                setcookie('password', $password, time() + 60 * 60 * 24 * 14);
            } else {
                // チェックがなければクッキーを消す（ここも重要）
                setcookie('email', '', time() - 60*60*24*14);
                setcookie('password', '', time() - 60*60*24*14);
            }
           
            header('Location: index.php');
            exit();
        } else {
            $error['login'] = 'failed';
        }
    } else {
        $error['login'] = 'blank';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
</head>

<link rel="stylesheet" href="./css/style.css">
<section class="logo">
    <img src="logo/kumocchi_logo2.png" alt="くもっちロゴ">
</section>

<body>
    <main class="registration">
    <div id="lead">
        <p>メールアドレスとパスワードを記入してログインしてください。</p>
        <p>入会手続きがまだの方はこちらからどうぞ。</p>
        <p>&raquo; <a href="join/">入会手続きをする</a></p>
    </div>
    <form action="" method="post">
        <dl>
            <dt>メールアドレス</dt>
            <dd>
                <input type="text" name="email" size="35" maxlength="255"
                value="<?php echo htmlspecialchars($_POST['email']?? '', ENT_QUOTES); ?>" />
                <?php if (isset($error['login']) && $error['login'] == 'blank'): ?>
                <p class="error">* メールアドレスとパスワードをご記入ください。</p>
                    <?php endif; ?>


                <?php if (isset($error['login']) && $error['login'] == 'failed'): ?>
                <p class="error">ログインに失敗しました。正しくご記入ください。</p>
                <?php endif; ?>
            </dd>
            <dt>パスワード</dt>
            <dd>
                <input type="password" name="password" size="35" maxlength="255"
                value="<?php echo htmlspecialchars($_POST['password'] ?? '',ENT_QUOTES); ?>" />
            </dd>
            <dt>ログイン情報の記録</dt>
            <dd>
                <input id="save" type="checkbox" name="save" value="on">
                <label for="save">次回からは自動的にログインする</label>
            </dd>
        </dl>
        <div><input type="submit" value="ログインする" /></div>
    </form>
    </main>
</body>
</html>