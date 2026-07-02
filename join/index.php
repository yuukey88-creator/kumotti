<link rel="stylesheet" href="../css/style.css">

<?php
session_start();
require('../dbconnect.php');

$error = [];

if(!empty($_POST)) {
    // エラー項目の確認
    if ($_POST['name'] == '') {
        $error['name'] = 'blank';
    }
    if ($_POST['email'] == '') {
        $error['email'] = 'blank';
    }
    if (strlen($_POST['password']) < 4) {
        $error['password'] = 'length';
    }
    if ($_POST['password'] == '') {
        $error['password'] = 'blank';
    }
    $fileName = $_FILES['image']['name'];

    if (!empty($_FILES['image']['name'])) {
        $image = date('YmdHis') . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], '../member_picture/' .$image);
    } else {
        $image = 'default.png';
    }  

    // 重複アカウントのチェック
    if (empty($error)) {
        $member = $db->prepare('SELECT COUNT(*) AS cnt FROM members WHERE email=?');
        $member->execute(array($_POST['email']));
        $record = $member->fetch();
        if ($record['cnt'] > 0) {
            $error['email'] = 'duplicate';
        }
    }

    if (empty($error)) {
    $_SESSION['join'] = $_POST;
    $_SESSION['join']['image'] = $image;
    header('Location: check.php');
    exit();
    }
}

// 書き直し
if (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'rewrite') {
    $_POST = $_SESSION['join'];
    $error['rewrite'] = true;
}
?>
<section class="logo">
    <img src="../logo/kumocchi_logo2.png" alt="くもっちロゴ">
</section>

<main class="registration">
<p>次のフォームに必要事項をご記入ください。</p>
<form action="" method="post" enctype="multipart/form-data">
    <dl>
        <dt>ニックネーム<span class="required">必須</span>
        <span style="font-size: 12px; color: #666;">※25文字以内</span></dt>
        <dd>
            <input type="text" name="name" size="35" maxlength="255" value="<?php echo htmlspecialchars($_POST['name']?? '', ENT_QUOTES); ?>"/>
            <?php if (!empty($error['name']) && $error['name'] === 'blank'): ?>
            <p class="error">*ニックネームを入力してください</p>
            <?php endif; ?>
        </dd>
        <dt>メールアドレス<span class="required">必須</span></dt>
        <dd>
            <input type="text" name="email" size="35" maxlength="255" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" />
            <?php if (!empty($error['email']) && $error['email'] === 'blank'): ?>
            <p class="error">*メールアドレスを入力してください</p>
            <?php endif ?>
            <?php if (!empty($error['email']) && $error['email'] === 'duplicate'): ?>
            <p class="error">*指定されたメールアドレスはすでに登録されています</p>
            <?php endif ?>
           
        </dd>
        <dt>パスワード<span class="required">必須</span>
        <span style="font-size: 12px; color: #666;">※4文字以上</span></dt>
        <dd>
            <input type="password" name="password" size="10" maxlength="20"  value="<?php echo htmlspecialchars($_POST['password']?? '', ENT_QUOTES); ?>" />
            <?php if (!empty($error['password']) && $error['password'] === 'blank'): ?>
            <p class="error">* パスワードを入力してください</p>
            <?php endif ?>
            <?php if (!empty($error['password']) && $error['password'] === 'length'): ?>
            <p class="error">* パスワードは4文字以上入力してください</p>
            <?php endif ?>
       
        </dd>
        <dt>
            アイコン
            <span style="font-size: 12px; color: #666;">※指定がない場合はデフォルトの写真になります</span>
        </dt>
        <dd>
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


            <?php if (!empty($error['image']) && $error['image'] === 'type'): ?>
            <p class="error">*写真などは「.gif」または「.jpg」の画像を指定してください</p>
            <?php endif ?>
        </dd>
    </dl>
    <div><input type="submit" value="入力内容を確認する"></div>
</form>
</main>

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