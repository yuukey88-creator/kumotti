<?php
session_start();
require('dbconnect.php');

// ログインしていない場合は戻す
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$id = $_SESSION['id'];
$error = [];

// 現在の登録情報を取得（フォームの初期値用）
$members = $db->prepare('SELECT name, email, picture FROM members WHERE id=?');
$members->execute(array($id));
$member = $members->fetch();

if (!empty($_POST)) {
    // エラー項目の確認
    if ($_POST['name'] == '') {
        $error['name'] = 'blank';
    }
    if ($_POST['email'] == '') {
        $error['email'] = 'blank';
    }
    // パスワードは「変更する場合のみ」チェック
    if (!empty($_POST['password']) && strlen($_POST['password']) < 4) {
        $error['password'] = 'length';
    }

    // 画像的のチェック
    $fileName = $_FILES['image']['name'];
    if (!empty($fileName)) {
        $ext = substr($fileName, -3);
        if ($ext != 'jpg' && $ext != 'gif' && $ext != 'png') {
            $error['image'] = 'type';
        }
    }

    // 重複アカウント的のチェック（自分以外の重複を確認）
    if (empty($error)) {
        $duplicate = $db->prepare('SELECT COUNT(*) AS cnt FROM members WHERE email=? AND id <> ?');
        $duplicate->execute(array($_POST['email'], $id));
        $record = $duplicate->fetch();
        if ($record['cnt'] > 0) {
            $error['email'] = 'duplicate';
        }
    }

    // 更新処理
    if (empty($error)) {
        // 画像アップロードがある場合
        if (!empty($fileName)) {
            $image = date('YmdHis') . $fileName;
            move_uploaded_file($_FILES['image']['tmp_name'], 'member_picture/' . $image);
            $stmt = $db->prepare('UPDATE members SET picture=? WHERE id=?');
            $stmt->execute(array($image, $id));
        }

        // 名前とメール的の更新
        $stmt = $db->prepare('UPDATE members SET name=?, email=? WHERE id=?');
        $stmt->execute(array($_POST['name'], $_POST['email'], $id));

        // パスワード入力がある場合のみ更新
        if (!empty($_POST['password'])) {
            $stmt = $db->prepare('UPDATE members SET password=? WHERE id=?');
            $stmt->execute(array(sha1($_POST['password']), $id));
        }

        header('Location: index.php'); // 完了後の遷移先
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

<link rel="stylesheet" href="./css/style.css">
<section class="logo">
    <img src="logo/kumocchi_logo2.png" alt="くもっちロゴ">
</section>
<main class="registration">

<p>会員情報を編集してください。</p>
<form action="" method="post" enctype="multipart/form-data">
    <dl>
        <dt>ニックネーム<span class="required">必須</span>
        <span style="font-size: 12px; color: #666;">※25文字以内</span></dt>
        <dd>
            <input type="text" name="name" size="35" maxlength="255" value="<?php echo htmlspecialchars($_POST['name'] ?? $member['name'], ENT_QUOTES); ?>"/>
            <?php if (isset($error['name']) && $error['name'] == 'blank'): ?>
                <p class="error" style="color:red;">*ニックネームを入力してください</p>
            <?php endif; ?>
        </dd>

        <dt>メールアドレス<span class="required">必須</span></dt>
        <dd>
            <input type="text" name="email" size="35" maxlength="255" value="<?php echo htmlspecialchars($_POST['email'] ?? $member['email'], ENT_QUOTES); ?>" />
            <?php if (isset($error['email']) && $error['email'] == 'blank'): ?>
                <p class="error" style="color:red;">*メールアドレスを入力してください</p>
            <?php endif; ?>
            <?php if (isset($error['email']) && $error['email'] == 'duplicate'): ?>
                <p class="error" style="color:red;">*指定されたメールアドレスはすでに登録されています</p>
            <?php endif; ?>
        </dd>

        <dt>パスワード（変更する場合のみ入力）</dt>
        <dd>
            <input type="password" name="password" size="10" maxlength="20" value="" />
            <?php if (isset($error['password']) && $error['password'] == 'length'): ?>
                <p class="error" style="color:red;">*パスワードは4文字以上入力してください</p>
            <?php endif; ?>
        </dd>

        <dt>アイコン</dt>
        <dd>
            <p>現在の画像: <img src="member_picture/<?php echo htmlspecialchars($member['picture'], ENT_QUOTES); ?>" width="50" /></p>
           
            <div style="display: flex; align-items: flex-end; gap: 15px;">
                                <label class="file-label" style="margin: 0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" class="action-icon" style="width: 30px; height: 30px; fill: #4a90e2; cursor: pointer;">
                                        <path d="M160 144C151.2 144 144 151.2 144 160L144 480C144 488.8 151.2 496 160 496L480 496C488.8 496 496 488.8 496 480L496 160C496 151.2 488.8 144 480 144L160 144zM96 160C96 124.7 124.7 96 160 96L480 96C515.3 96 544 124.7 544 160L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 160zM224 192C241.7 192 256 206.3 256 224C256 241.7 241.7 256 224 256C206.3 256 192 241.7 192 224C192 206.3 206.3 192 224 192zM360 264C368.5 264 376.4 268.5 380.7 275.8L460.7 411.8C465.1 419.2 465.1 428.4 460.8 435.9C456.5 443.4 448.6 448 440 448L200 448C191.1 448 182.8 443 178.7 435.1C174.6 427.2 175.2 417.6 180.3 410.3L236.3 330.3C240.8 323.9 248.1 320.1 256 320.1C263.9 320.1 271.2 323.9 275.7 330.3L292.9 354.9L339.4 275.9C343.7 268.6 351.6 264.1 360.1 264.1z"/>
                                    </svg>
                                    <input type="file" name="image" id="image-upload" size="35" style="display:none;" accept="image/*">
                                </label>


                                <div id="preview-box" style="display: none; position: relative;">
                                    <img id="image-preview" src="" style="width: 100px;height: 100px;object-fit: cover;border-radius: 50%;">
                                    <div id="remove-preview" style="display: none; position: absolute; top: -10px; right: -10px; cursor: pointer; background: white; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" style="width: 25px; height: 25px; fill: #ff4d4d;">
                                            <path d="M320 112C434.9 112 528 205.1 528 320C528 434.9 434.9 528 320 528C205.1 528 112 434.9 112 320C112 205.1 205.1 112 320 112zM320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM231 231C221.6 240.4 221.6 255.6 231 264.9L286 319.9L231 374.9C221.6 384.3 221.6 399.5 231 408.8C240.4 418.1 255.6 418.2 264.9 408.8L319.9 353.8L374.9 408.8C384.3 418.2 399.5 418.2 408.8 408.8C418.1 399.4 418.2 384.2 408.8 374.9L353.8 319.9L408.8 264.9C418.2 255.5 418.2 240.3 408.8 231C399.4 221.7 384.2 221.6 374.9 231L319.9 286L264.9 231C255.5 221.6 240.3 221.6 231 231z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
            <?php if (isset($error['image']) && $error['image'] == 'type'): ?>
                <p class="error" style="color:red;">*画像は「.gif」「.jpg」「.png」を指定してください</p>
            <?php endif; ?>
        </dd>
    </dl>
    <div><input type="submit" value="変更を保存する"></div>
</form>
</main>
</body>
<script>
    document.getElementById('image-upload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const previewBox = document.getElementById('preview-box');
    const previewImg = document.getElementById('image-preview');
    const removeBtn = document.getElementById('remove-preview');
   
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            previewImg.src = event.target.result;
            previewBox.style.display = 'block';
            removeBtn.style.display = 'block'; // バツ印を出す
        };
        reader.readAsDataURL(file);
    }
});

document.getElementById('remove-preview').addEventListener('click', function() {
    document.getElementById('image-upload').value = "";
    document.getElementById('preview-box').style.display = 'none';
    this.style.display = 'none';
});
</script>

</html>