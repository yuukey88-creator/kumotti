<?php
try {
    $db = new PDO( 'mysql:host=sql103.infinityfree.com;dbname=if0_42288456_mini_bbs;charset=utf8mb4',
    'if0_42288456',
    'パスワード'
    );
}
catch (PDOException $e ) {
    echo 'DB接続エラー： '.$e -> getMessage();
}
?>
