<?php
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}
?>

<link rel="stylesheet" href="./css/style.css">
<section class="logo">
    <img src="logo/kumocchi_logo2.png" alt="くもっちロゴ">
</section>

<main class="registration">
    <h2>退会確認</h2>
        <p>本当に退会しますか？<br>
        退会すると投稿もすべて削除されます。</p>

            <form action="withdraw_done.php" method="post">
                <input type="submit" value="退会する"
                    onclick="return confirm('本当に退会しますか？この操作は取り消せません。');">
            </form>

    <a href="index.php">キャンセル</a>
</main>