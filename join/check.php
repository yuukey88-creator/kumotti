<link rel="stylesheet" href="../css/style.css">

<?php
session_start();
require('../dbconnect.php');

if (!isset($_SESSION['join'])) {
    header('Location: index.php');
    exit();
}

if(!empty($_POST)){
    // 1. 登録処理
    $statement = $db->prepare('INSERT INTO members SET name=?, email=?, password=?, picture=?, created=NOW()');
    $statement->execute(array(
        $_SESSION['join']['name'],
        $_SESSION['join']['email'],
        sha1($_SESSION['join']['password']),
        $_SESSION['join']['image'],
    ));

    // 2. 【追加】今登録したばかりのIDを取得
    $lastId = $db->lastInsertId();

    // 3. 【追加】ログイン状態にする
    $_SESSION['id'] = $lastId;
    $_SESSION['time'] = time();
   
    // 4. 【追加】複数アカウントリストの初期化（これをしないとマイページの切替に出ません）
    $_SESSION['account_list'] = array($lastId);

    // 登録用の一時データを消去
    unset($_SESSION['join']);

    // 5. 【修正】thanks.php ではなく index.php（タイムライン）へ直接飛ばす
    header('Location: ../index.php');
    exit();
}
?>

<section class="logo">
    <img src="../logo/kumocchi_logo2.png" alt="くもっちロゴ">
</section>

<main class="registration">
<form action="" method="post">
    <input type="hidden" name="action" value="submit" />
    <dl>
        <dt>ニックネーム</dt>
        <dd>
        <?php echo htmlspecialchars($_SESSION['join']['name'], ENT_QUOTES); ?>
        </dd>
        <dt>メールアドレス</dt>
        <dd>
        <?php echo htmlspecialchars($_SESSION['join']['email'], ENT_QUOTES); ?>
        </dd>
        <dt>パスワード</dt>
        <dd>
        </dd>
        【表示されません】
        <dt>アイコン</dt>
        <dd>
        <img src="../member_picture/<?php echo htmlspecialchars($_SESSION['join']['image'], ENT_QUOTES); ?>" width="100" height="100" alt="" />
        </dd>
    </dl>
    <div><a href="index.php?action=rewrite">&laquo;&nbsp;書き直す</a> | <input type="submit" value="登録する" /></div>
</main>