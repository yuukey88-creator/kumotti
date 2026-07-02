<?php
session_start(); // ログインチェック等に必要
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 60*60*24*14 > time()) {
    $_SESSION['time'] = time();
    $stmt = $db->prepare('SELECT * FROM members WHERE id=?');
    $stmt->execute(array($_SESSION['id']));
    $member = $stmt->fetch();
} else {
    header('Location: login.php');
    exit();
}

if (empty($_REQUEST['id'])){
    header('Location: index.php'); exit();
}

// 返信が投稿された時の処理
if (!empty($_POST)) {
    if ($_POST['message'] !== '' || (!empty($_FILES['picture']['name']))){
        // 【修正】画像アップロードの処理を追加
        $image = '';
        if (!empty($_FILES['picture']['name'])) {
        $image = date('YmdHis') . $_FILES['picture']['name'];
        move_uploaded_file($_FILES['picture']['tmp_name'], 'post_picture/' . $image);
}
        // 【修正】INSERT文に image を追加
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, post_picture=?, reply_post_id=?, created=NOW()');
        $message->execute(array(
            $member['id'],
            $_POST['message'],
            $image, // ここで画像を保存
            $_POST['reply_post_id']
        ));

        header('Location: view.php?id=' . $_POST['reply_post_id']);
        exit();
    }
}

// 表示する投稿のID
$view_id = $_REQUEST['id'];

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p
    WHERE m.id=p.member_id
    AND (p.id=? OR p.reply_post_id=? OR p.id=(SELECT reply_post_id FROM posts WHERE id=?))
    ORDER BY p.created ASC');
$posts->execute(array($view_id, $view_id, $view_id));

$all_posts = $posts->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>スレッド</title>
    <link rel="stylesheet" href="css/style.css" />
   
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>スレッド一覧</h1>
        </div>
        <div id="content">
            <p>&laquo; <a href="index.php">一覧に戻る</a></p>

            <?php if (!empty($all_posts)): ?>
                <?php foreach ($all_posts as $p): ?>
                    <div class="msg <?php echo ($p['id'] == $view_id) ? 'current-post' : ''; ?>">
                        <img src="member_picture/<?php echo htmlspecialchars($p['picture'], ENT_QUOTES); ?>" width="48" height="48" class="member-icon"/>
                        <span class="name">ID：<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?></span>
                       
                        <p class="post-text is-limit"><?php echo htmlspecialchars($p['message'], ENT_QUOTES); ?></p>
                        <button type="button" class="read-more-btn">続きを読む</button>
                        
                        <?php if (!empty($p['post_picture'])): ?>
                            <p><img src="post_picture/<?php echo htmlspecialchars($p['post_picture'], ENT_QUOTES); ?>" width="200" alt="" /></p>
                        <?php endif; ?>

                        <p class="day">
                            <a title="このスレッドのトップへ戻る"
                                href="view.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['created'], ENT_QUOTES); ?></a>
                            <a href="#reply-area" class="reply-link" onclick="setReplyName('<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>')">この投稿に返信</a>
                   
                    <?php if ($_SESSION['id'] == $p['member_id']): ?>
                        <a title="この投稿を削除する" href="delete.php?id=<?php echo htmlspecialchars($p['id']); ?>"
                        class="delete-link"
                        onclick="return confirm('本当に削除しますか');">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" class="action-icon delete-icon">
                                <path d="M232.7 69.9L224 96L128 96C110.3 96 96 110.3 96 128C96 145.7 110.3 160 128 160L512 160C529.7 160 544 145.7 544 128C544 110.3 529.7 96 512 96L416 96L407.3 69.9C402.9 56.8 390.7 48 376.9 48L263.1 48C249.3 48 237.1 56.8 232.7 69.9zM512 208L128 208L149.1 531.1C150.7 556.4 171.7 576 197 576L443 576C468.3 576 489.3 556.4 490.9 531.1L512 208z"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                    </p>
                    </div>
                    <?php
                    if ($p['id'] == $view_id) {
                        $current_main_post = $p;
                    }
                    ?>
                <?php endforeach; ?>

                <hr>

                <div id="reply-area" class="msg" style="margin-top: 30px;">
                    <p style="margin-bottom: 15px; font-weight: bold; color: #555;">この投稿に返信する：</p>
                    <form action="view.php?id=<?php echo htmlspecialchars($view_id, ENT_QUOTES); ?>" method="post" enctype="multipart/form-data">
                        <textarea name="message" id="reply-input" class="post-text" style="width: 100%; border: 1px solid #eee; border-radius: 20px; padding: 15px; box-sizing: border-box;">@<?php echo htmlspecialchars($current_main_post['name'], ENT_QUOTES); ?> </textarea>
                       
                        <input type="hidden" name="reply_post_id" value="<?php echo htmlspecialchars($view_id, ENT_QUOTES); ?>">

                        <div class="reply-footer">
                            <div style="display: flex; align-items: flex-end; gap: 15px;">
                                <label class="file-label" style="margin: 0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" class="action-icon" style="width: 30px; height: 30px; fill: #4a90e2; cursor: pointer;">
                                        <path d="M160 144C151.2 144 144 151.2 144 160L144 480C144 488.8 151.2 496 160 496L480 496C488.8 496 496 488.8 496 480L496 160C496 151.2 488.8 144 480 144L160 144zM96 160C96 124.7 124.7 96 160 96L480 96C515.3 96 544 124.7 544 160L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 160zM224 192C241.7 192 256 206.3 256 224C256 241.7 241.7 256 224 256C206.3 256 192 241.7 192 224C192 206.3 206.3 192 224 192zM360 264C368.5 264 376.4 268.5 380.7 275.8L460.7 411.8C465.1 419.2 465.1 428.4 460.8 435.9C456.5 443.4 448.6 448 440 448L200 448C191.1 448 182.8 443 178.7 435.1C174.6 427.2 175.2 417.6 180.3 410.3L236.3 330.3C240.8 323.9 248.1 320.1 256 320.1C263.9 320.1 271.2 323.9 275.7 330.3L292.9 354.9L339.4 275.9C343.7 268.6 351.6 264.1 360.1 264.1z"/>
                                    </svg>
                                    <input type="file" name="picture" id="image-upload" style="display:none;" accept="image/*">
                                </label>

                                <div id="preview-box">
                                    <img id="image-preview" src="">
                                    <div id="remove-preview" style="display: none; position: absolute; top: -10px; right: -10px; cursor: pointer; background: white; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" style="width: 25px; height: 25px; fill: #ff4d4d;">
                                            <path d="M320 112C434.9 112 528 205.1 528 320C528 434.9 434.9 528 320 528C205.1 528 112 434.9 112 320C112 205.1 205.1 112 320 112zM320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM231 231C221.6 240.4 221.6 255.6 231 264.9L286 319.9L231 374.9C221.6 384.3 221.6 399.5 231 408.8C240.4 418.1 255.6 418.2 264.9 408.8L319.9 353.8L374.9 408.8C384.3 418.2 399.5 418.2 408.8 408.8C418.1 399.4 418.2 384.2 408.8 374.9L353.8 319.9L408.8 264.9C418.2 255.5 418.2 240.3 408.8 231C399.4 221.7 384.2 221.6 374.9 231L319.9 286L264.9 231C255.5 221.6 240.3 221.6 231 231z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <input type="submit" value="返信する" class="submit-btn">
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <p>その投稿は削除されたか、URLが間違えています</p>
            <?php endif; ?>
        </div>
    </div>
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

window.addEventListener('load', () => {
    document.querySelectorAll('.post-text').forEach(textElement => {
        const button = textElement.nextElementSibling;
        // 判定のために、一瞬だけ制限を外して高さを測る
        textElement.classList.remove('is-limit');
        const fullHeight = textElement.scrollHeight; // 本来の高さ
        textElement.classList.add('is-limit'); // 制限を戻す
        const limitedHeight = textElement.clientHeight; // 5行の高さ
        // 本来の高さが制限時の高さより大きければ、ボタンを出す
        if (fullHeight > limitedHeight) {
            button.style.display = 'block';
        }
        button.addEventListener('click', () => {
            if (textElement.classList.contains('is-limit')) {
                textElement.classList.remove('is-limit');
                button.textContent = '閉じる';
            } else {
                textElement.classList.add('is-limit');
                button.textContent = '続きを読む';
            }
        });
    });
});


function setReplyName(name) {
    const textarea = document.getElementById('reply-input');
    textarea.value = '@' + name + ' '; // テキストエリアの中身を「@名前 」に書き換える
    textarea.focus(); // 入力しやすいようにカーソルを合わせる
}
</script>
</html>