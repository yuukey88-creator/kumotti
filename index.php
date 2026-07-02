<?php
session_start();
require('dbconnect.php');
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . '年前';
    if ($diff->m > 0) return $diff->m . 'ヶ月前';
    if ($diff->d > 0) return $diff->d . '日前';
    if ($diff->h > 0) return $diff->h . '時間前';
    if ($diff->i > 0) return $diff->i . '分前';
    return 'たった今';
}
if(isset($_SESSION['id']) && $_SESSION['time'] +60*60*24*14 > time()){
    // ログインしている
    $_SESSION['time'] = time();
    $members = $db -> prepare('SELECT*FROM members WHERE id=?');
    $members -> execute(array($_SESSION['id']));
    $member = $members -> fetch();
}
else {
    // ログインしていない
    header('Location: login.php'); exit();
}
// 投稿を記録する
if (!empty($_POST)){
if ($_POST['message'] !== '' || (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK)) {
    $post_image = '';
    if (!empty($_FILES['picture']['name'])) {
        // 投稿写真は post_picture フォルダに保存
        $post_image = date('YmdHis') . $_FILES['picture']['name'];
        move_uploaded_file($_FILES['picture']['tmp_name'], 'post_picture/' . $post_image);
    }
    $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, post_picture=?, created=NOW()');
    $message->execute(array(
        $member['id'],
        $_POST['message'],
        $_POST['reply_post_id'],
        $post_image
    ));
    // 2. 今日の日付を取得
    $today = date('Y-m-d');
    // 3. ランキング更新
    // 投稿件数は常に加算するが、ポイント加算は最初の5件まで（5件×5pt＝25pt上限）
    $update_ranking = $db->prepare("
    INSERT INTO ranking (member_id, points, post_count, last_post_date)
    VALUES (?, 5, 1, ?)
    ON DUPLICATE KEY UPDATE
        points = IF(post_count < 5, points + 5, points),
        post_count = post_count + 1,
        last_post_date = VALUES(last_post_date)
    ");
    $update_ranking->execute(array($member['id'], $today));

    header('Location: index.php'); exit();
}
}
// 4. 投稿一覧を取得（表示するデータの件数と合わせる）
$page = isset($_REQUEST['page']) ? max(intval($_REQUEST['page']), 1) : 1;

// 表示対象が posts だけなら COUNT(*) FROM posts に統一します
$counts = $db->query('
    SELECT
    (SELECT COUNT(*) FROM posts WHERE reply_post_id = 0) +
    (SELECT COUNT(*) FROM reposts r JOIN posts p ON r.post_id = p.id)
    AS cnt
');
$cnt = $counts->fetch();
$total_posts = $cnt['cnt'];
$maxPage = max(ceil($total_posts / 5), 1);
$page = min($page, $maxPage);
$start = ($page - 1) * 5;
// --- 投稿一覧を取得（リポスト日時を優先して並び替え） ---
// --- 投稿一覧を取得（Repost数カウントを追加） ---
$posts = $db->prepare('
    (SELECT m.name, m.picture, p.*,
        SUM(f.count) AS fav_count,
        (SELECT COUNT(*) FROM reposts WHERE post_id = p.id) AS rp_count,
        (SELECT COUNT(*) FROM posts AS reply WHERE reply.reply_post_id = p.id) AS reply_count,
        NULL AS repost_by, p.created AS sort_date
     FROM members m, posts p
     LEFT JOIN favorites f ON p.id = f.post_id
     WHERE m.id = p.member_id
     AND p.reply_post_id = 0
     GROUP BY p.id)
    UNION ALL
    (SELECT m.name, m.picture, p.*,
        (SELECT SUM(count) FROM favorites WHERE post_id = p.id) AS fav_count,
        (SELECT COUNT(*) FROM reposts WHERE post_id = p.id) AS rp_count,
        (SELECT COUNT(*) FROM posts AS reply WHERE reply.reply_post_id = p.id) AS reply_count, -- ここを追加
        r_m.name AS repost_by, r.created AS sort_date
     FROM reposts r
     JOIN posts p ON r.post_id = p.id
     JOIN members m ON p.member_id = m.id
     JOIN members r_m ON r.member_id = r_m.id)
    ORDER BY sort_date DESC
    LIMIT ?, 5
');

$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();
// 返信の場合
$message = '';
if(isset($_REQUEST['res'])){
    $response = $db -> prepare('SELECT m.name, m.picture, p.* FROM members m,posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response -> execute(array($_REQUEST['res']));
    $table = $response -> fetch();
    $message = '@'.$table['name'].''.$table['message'];
}

?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link rel="stylesheet" href="css/style.css">
<title>くもっち|HOME</title>
</head>
<body>
<button class="menu-btn" onclick="toggleMenu()">☰</button>
<main>
<div class="container">
    <!-- 画面左側 -->
    <div class="left" style="max-height: auto; overflow-y: auto; padding-right: 10px;">
    <div style="background: #f0f8ff; padding: 10px; border-radius: 10px; margin-bottom: 15px; border: 1px solid #d0e8ff;">
        <p style="margin: 0 0 5px 0; font-size: 0.9em;">ログイン中：</p>
        <p style="margin: 0 0 10px 0;"><strong><?php echo htmlspecialchars($member['name'], ENT_QUOTES); ?></strong> さん</p>
        <?php if (isset($_SESSION['account_list']) && count($_SESSION['account_list']) > 1): ?>
            <p style="font-size: 0.8em; color: #666; margin: 5px 0; border-top: 1px dashed #ccc; padding-top: 5px;">アカウントを切り替える：</p>
            <?php
            foreach ($_SESSION['account_list'] as $id):
                if ($id == $_SESSION['id']) continue;
                $stmt = $db->prepare('SELECT name FROM members WHERE id=?');
                $stmt->execute(array($id));
                $other = $stmt->fetch();
                if ($other):
            ?>
            <a href="switch.php?id=<?php echo $id; ?>">
            👤 <?php echo htmlspecialchars($other['name'], ENT_QUOTES); ?>
            </a>
            <?php
                endif;
            endforeach;
            ?>
        <?php endif; ?>
        <div>
            <a href="login.php">+ 別のアカウントを追加</a>
        </div>
    </div>
    <div class="menu-links">
        <a href="mypage.php">マイページ</a><br>
        <a href="ranking.php">ランキング</a><br>
        <a href="update.php">アカウント情報を変更する</a><br>
        <a href="logout.php">ログアウト</a><br>
        <a href="withdrawal.php">退会する</a>
    </div>
</div>
<!-- 画面右側 -->
<div class="right">
    <form action="index.php" method="post" enctype="multipart/form-data" class="post-form">
        <div class="input-area">
            <p class="greeting"><?php echo htmlspecialchars($member['name'],ENT_QUOTES);?>さん、メッセージをどうぞ</p>
            <textarea name="message" placeholder="テキストを入力..."><?php echo htmlspecialchars($message,ENT_QUOTES);?></textarea>
            <input type="hidden" name="reply_post_id" value="<?php echo htmlspecialchars($_REQUEST['res'] ?? '', ENT_QUOTES); ?>" />
        </div>
   
        <div class="post-footer">
            <div class="post-actions" style="display: flex; align-items: flex-end; gap: 15px;">
                <label title="画像を投稿する" class="file-label" style="margin: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" class="action-icon">
                        <path d="M160 144C151.2 144 144 151.2 144 160L144 480C144 488.8 151.2 496 160 496L480 496C488.8 496 496 488.8 496 480L496 160C496 151.2 488.8 144 480 144L160 144zM96 160C96 124.7 124.7 96 160 96L480 96C515.3 96 544 124.7 544 160L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 160zM224 192C241.7 192 256 206.3 256 224C256 241.7 241.7 256 224 256C206.3 256 192 241.7 192 224C192 206.3 206.3 192 224 192zM360 264C368.5 264 376.4 268.5 380.7 275.8L460.7 411.8C465.1 419.2 465.1 428.4 460.8 435.9C456.5 443.4 448.6 448 440 448L200 448C191.1 448 182.8 443 178.7 435.1C174.6 427.2 175.2 417.6 180.3 410.3L236.3 330.3C240.8 323.9 248.1 320.1 256 320.1C263.9 320.1 271.2 323.9 275.7 330.3L292.9 354.9L339.4 275.9C343.7 268.6 351.6 264.1 360.1 264.1z"/>
                    </svg>
                    <input type="file" name="picture" id="image-upload" style="display:none;" accept="image/*">
                </label>
           
                <div id="preview-box" style="position: relative; display: none;">
                    <img id="image-preview" src="" style="width: 100px; height: 100px; object-fit: cover; border-radius: 15px; border: 2px solid #4a90e2;">
                    <div id="remove-preview" style="position: absolute; top: -10px; right: -10px; cursor: pointer; background: white; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" style="width: 25px; height: 25px; fill: #ff4d4d;">
                            <path d="M320 112C434.9 112 528 205.1 528 320C528 434.9 434.9 528 320 528C205.1 528 112 434.9 112 320C112 205.1 205.1 112 320 112zM320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM231 231C221.6 240.4 221.6 255.6 231 264.9L286 319.9L231 374.9C221.6 384.3 221.6 399.5 231 408.8C240.4 418.1 255.6 418.2 264.9 408.8L319.9 353.8L374.9 408.8C384.3 418.2 399.5 418.2 408.8 408.8C418.1 399.4 418.2 384.2 408.8 374.9L353.8 319.9L408.8 264.9C418.2 255.5 418.2 240.3 408.8 231C399.4 221.7 384.2 221.6 374.9 231L319.9 286L264.9 231C255.5 221.6 240.3 221.6 231 231z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <input type="submit" value="投稿する" class="submit-btn" />
        </div>
    </form>

    <?php
    foreach($posts as $post): ?>
    <div class="msg <?php echo ($post['reply_post_id'] > 0) ? 'is-reply' : ''; ?>">
        <?php if (!empty($post['repost_by'])): ?>
            <p style="color: #4a90e2; font-size: 0.8em; margin-bottom: 5px;">
                <svg style="width:12px; height:12px; fill:currentColor;" viewBox="0 0 640 640">...</svg>
                <?php echo htmlspecialchars($post['repost_by'], ENT_QUOTES); ?> さんがリポストしました
            </p>
        <?php endif; ?>
   
        <img src="member_picture/<?php echo htmlspecialchars($post['picture'], ENT_QUOTES); ?>"  class="member-icon" />
        <span class="name"> ID：<?php echo htmlspecialchars($post['name'], ENT_QUOTES); ?> </span>
   
        <p class="post-text is-limit"><?php echo htmlspecialchars($post['message'], ENT_QUOTES); ?></p>
   
        <button type="button" class="read-more-btn">続きを読む</button>


        <?php if (!empty($post['post_picture'])): ?>
            <p><img src="post_picture/<?php echo htmlspecialchars($post['post_picture'], ENT_QUOTES); ?>" width="200" alt="" /></p>
        <?php endif; ?>
   
        <p class="day">
        <a title="スレッドを見る"
            href="view.php?id=<?php echo htmlspecialchars($post['id'],ENT_QUOTES);?>"><?php echo time_elapsed_string($post['created']);?></a>
            <?php if ($post['reply_post_id'] > 0): ?>
        <a href="view.php?id=<?php echo htmlspecialchars ($post['reply_post_id'], ENT_QUOTES); ?>">返信元のメッセージ</a>
            <?php endif; ?>
            <?php
            // 自分がこの投稿をリポスト済みかチェックする判定
            // $post['repost_by'] が自分の名前なら「取り消し」へ、そうでなければ「リポスト」へ
            $is_my_rp = ($post['repost_by'] === $member['name']);
            $rp_url = $is_my_rp ? 'unrepost.php' : 'repost.php';
            $rp_title = $is_my_rp ? 'リポストを取り消す' : 'この投稿をリポスト(拡散)する';
            ?>
<a title="<?php echo $rp_title; ?>"
    href="<?php echo $rp_url; ?>?id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>"
    class="rp-link <?php echo $is_my_rp ? 'is-active' : ''; ?>"
    style="text-decoration: none; margin-left: 10px;">
   
    <svg class="fa-rp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
        <path d="M150.6 105.4C138.1 92.9 117.8 92.9 105.3 105.4L41.3 169.4C32.1 178.6 29.4 192.3 34.4 204.3C39.4 216.3 51.1 224 64 224L96 224L96 448C96 501 139 544 192 544L320 544C337.7 544 352 529.7 352 512C352 494.3 337.7 480 320 480L192 480C174.3 480 160 465.7 160 448L160 224L192 224C204.9 224 216.6 216.2 221.6 204.2C226.6 192.2 223.8 178.5 214.7 169.3L150.7 105.3zM489.4 534.6C501.9 547.1 522.2 547.1 534.7 534.6L598.7 470.6C607.9 461.4 610.6 447.7 605.6 435.7C600.6 423.7 588.9 416 576 416L544 416L544 192C544 139 501 96 448 96L320 96C302.3 96 288 110.3 288 128C288 145.7 302.3 160 320 160L448 160C465.7 160 480 174.3 480 192L480 416L448 416C435.1 416 423.4 423.8 418.4 435.8C413.4 447.8 416.2 461.5 425.3 470.7L489.3 534.7z"/>
    </svg>
    <span class="rp-count"><?php echo ($post['rp_count'] > 0) ? $post['rp_count'] : 0; ?></span>
    <span class="rp-label"></span>
</a>
<a title="この投稿にいいね！する"
    href="#" class="fav-link"
    data-id="<?php echo $post['id']; ?>"
    style="text-decoration: none;">
    <svg class="fa-heart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
        <path d="M442.9 144C415.6 144 389.9 157.1 373.9 179.2L339.5 226.8C335 233 327.8 236.7 320.1 236.7C312.4 236.7 305.2 233 300.7 226.8L266.3 179.2C250.3 157.1 224.6 144 197.3 144C150.3 144 112.2 182.1 112.2 229.1C112.2 279 144.2 327.5 180.3 371.4C221.4 421.4 271.7 465.4 306.2 491.7C309.4 494.1 314.1 495.9 320.2 495.9C326.3 495.9 331 494.1 334.2 491.7C368.7 465.4 419 421.3 460.1 371.4C496.3 327.5 528.2 279 528.2 229.1C528.2 182.1 490.1 144 443.1 144zM335 151.1C360 116.5 400.2 96 442.9 96C516.4 96 576 155.6 576 229.1C576 297.7 533.1 358 496.9 401.9C452.8 455.5 399.6 502 363.1 529.8C350.8 539.2 335.6 543.9 320 543.9C304.4 543.9 289.2 539.2 276.9 529.8C240.4 502 187.2 455.5 143.1 402C106.9 358.1 64 297.7 64 229.1C64 155.6 123.6 96 197.1 96C239.8 96 280 116.5 305 151.1L320 171.8L335 151.1z"/>
    </svg>
    <span class="fav-count"><?php echo ($post['fav_count'] > 0) ? $post['fav_count'] : 0; ?></span>
    <span class="fav-label"></span>
</a>
<a title="この投稿にコメントする" href="view.php?id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>#reply-area" class="reply-link" style="text-decoration: none; margin-left: 10px;">
    <svg class="fa-reply-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
        <path d="M115.9 448.9C83.3 408.6 64 358.4 64 304C64 171.5 178.6 64 320 64C461.4 64 576 171.5 576 304C576 436.5 461.4 544 320 544C283.5 544 248.8 536.8 217.4 524L101 573.9C97.3 575.5 93.5 576 89.5 576C75.4 576 64 564.6 64 550.5C64 546.2 65.1 542 67.1 538.3L115.9 448.9zM153.2 418.7C165.4 433.8 167.3 454.8 158 471.9L140 505L198.5 479.9C210.3 474.8 223.7 474.7 235.6 479.6C261.3 490.1 289.8 496 319.9 496C437.7 496 527.9 407.2 527.9 304C527.9 200.8 437.8 112 320 112C202.2 112 112 200.8 112 304C112 346.8 127.1 386.4 153.2 418.7z"/>
    </svg>
    <span class="reply-count"><?php echo ($post['reply_count'] > 0) ? $post['reply_count'] : 0; ?></span>
    <span class="reply-label"></span>
</a>

<?php if ($_SESSION['id'] == $post['member_id']): ?>
    <a title="この投稿を削除する" href="delete.php?id=<?php echo htmlspecialchars($post['id']); ?>"
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
endforeach;
?>
<?php if ($maxPage > 1): ?>
<div class="pagination">

<?php if ($page > 1): ?>
<a href="index.php?page=1">&laquo;</a>
<a href="index.php?page=<?php echo $page-1; ?>">&lsaquo;</a>
<?php endif; ?>

<?php
$range = 1;

$startPage = max(1, $page - $range);
$endPage   = min($maxPage, $page + $range);

if ($startPage > 1) {
    echo '<a href="index.php?page=1">1</a>';
    if ($startPage > 2) echo '<span class="dots">...</span>';
}

for ($i = $startPage; $i <= $endPage; $i++) {
    if ($i == $page) {
        echo '<span class="current">'.$i.'</span>';
    } else {
        echo '<a href="index.php?page='.$i.'">'.$i.'</a>';
    }
}

if ($endPage < $maxPage) {
    if ($endPage < $maxPage - 1) echo '<span class="dots">...</span>';
    echo '<a href="index.php?page='.$maxPage.'">'.$maxPage.'</a>';
}
?>

<?php if ($page < $maxPage): ?>
<a href="index.php?page=<?php echo $page+1; ?>">&rsaquo;</a>
<a href="index.php?page=<?php echo $maxPage; ?>">&raquo;</a>
<?php endif; ?>

</div>
<?php endif; ?>
    </div>
</div>
</main>
</body>  
<script>
document.querySelectorAll('.fav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault(); // ページ移動を止める
        const postId = link.dataset.id;
        const countSpan = link.querySelector('.fav-count');
        // FormDataを使ってPHPにIDを送信
        const fd = new FormData();
        fd.append('id', postId);
        fetch('ajax_favorite.php', {
            method: 'POST',
            body: fd
        })
        .then(response => response.text())
        .then(newCount => {
            // PHPから返ってきた「新しい数字」に書き換える
            countSpan.textContent = newCount;
        });
    });
});
const menuBtn = document.querySelector('.menu-btn');
const leftMenu = document.querySelector('.left');
// 1. 背景レイヤー（オーバーレイ）を準備
let overlay = document.querySelector('.menu-overlay');
if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'menu-overlay';
    document.body.appendChild(overlay);
}
// 2. ボタンクリック時の処理
menuBtn.addEventListener('click', () => {
    // クラスを付け外しして「開閉」を表現
    leftMenu.classList.toggle('open');
    overlay.classList.toggle('show');
    menuBtn.classList.toggle('active'); // 3本線 ⇔ × の切り替え
});
// 3. 背景（オーバーレイ）をクリックしたら閉じる
overlay.addEventListener('click', () => {
    leftMenu.classList.remove('open');
    overlay.classList.remove('show');
    menuBtn.classList.toggle('active');
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
            removeBtn.classList.add('show'); // バツ印を表示
        };
        reader.readAsDataURL(file);
    }
});
// バツ印クリックでリセット
document.getElementById('remove-preview').addEventListener('click', function(e) {
    e.preventDefault(); // 親ラベルのクリックを防止
    document.getElementById('image-upload').value = ""; // 入力をリセット
    document.getElementById('preview-box').style.display = 'none';
    this.classList.remove('show'); // バツ印を隠す
});
</script>
</html>