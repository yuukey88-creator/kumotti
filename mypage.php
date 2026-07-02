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

$tab = $_GET['tab'] ?? 'posts';

if(isset($_SESSION['id']) && $_SESSION['time'] +60*60*24*14 > time()){
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
}else{
    header('Location: login.php');
    exit();
}

// --- 修正箇所：ページネーションの計算 ---
$page = $_REQUEST['page'] ?? 1;
$page = max(intval($page), 1);

if ($tab == 'posts') {
    $counts = $db->prepare("
        SELECT
        (SELECT COUNT(*) FROM posts WHERE member_id = ? AND reply_post_id = 0) +
        (SELECT COUNT(*) FROM reposts r JOIN posts p ON r.post_id = p.id WHERE r.member_id = ?)
        AS cnt
    ");
    $counts->execute([$member['id'], $member['id']]);
} elseif ($tab == 'likes') {
    // 「元記事が削除されていない」かつ「重複していない」いいね数を正確に数える
    $counts = $db->prepare("
        SELECT COUNT(DISTINCT f.post_id) AS cnt
        FROM favorites f
        JOIN posts p ON f.post_id = p.id
        WHERE f.member_id = ?
    ");
    $counts->execute([$member['id']]);
}

$cnt = $counts->fetch();
$total_posts = $cnt['cnt'];

$maxPage = max(ceil($total_posts / 5), 1);
$page = min($page, $maxPage);
$start = ($page - 1) * 5;

// --- 修正箇所：取得クエリ（返信を除外する条件を追加） ---
// --- 修正箇所：54行目〜 ---
if ($tab == 'posts') {
    $posts = $db->prepare('
        (SELECT m.name, m.picture, p.*,
            (SELECT SUM(count) FROM favorites WHERE post_id = p.id) AS fav_count,
            (SELECT COUNT(*) FROM reposts WHERE post_id = p.id) AS rp_count,
            (SELECT COUNT(*) FROM posts AS reply WHERE reply.reply_post_id = p.id) AS reply_count, -- 追加
            NULL AS repost_by, p.created AS sort_date
         FROM members m
         JOIN posts p ON m.id = p.member_id
         WHERE p.member_id = ? AND p.reply_post_id = 0)

        UNION ALL

        (SELECT m.name, m.picture, p.*,
            (SELECT SUM(count) FROM favorites WHERE post_id = p.id) AS fav_count,
            (SELECT COUNT(*) FROM reposts WHERE post_id = p.id) AS rp_count,
            (SELECT COUNT(*) FROM posts AS reply WHERE reply.reply_post_id = p.id) AS reply_count, -- 追加
            r_m.name AS repost_by, r.created AS sort_date
         FROM reposts r
         JOIN posts p ON r.post_id = p.id
         JOIN members m ON p.member_id = m.id
         JOIN members r_m ON r.member_id = r_m.id
         WHERE r.member_id = ?)

        ORDER BY sort_date DESC
        LIMIT ?, 5
    ');

    $posts->bindValue(1, $member['id'], PDO::PARAM_INT);
    $posts->bindValue(2, $member['id'], PDO::PARAM_INT);
    $posts->bindValue(3, $start, PDO::PARAM_INT);
    $posts->execute();
}

if($tab == 'likes'){
    $posts = $db->prepare('
        SELECT m.name, m.picture, p.*,
            (SELECT SUM(count) FROM favorites WHERE post_id = p.id) AS fav_count,
            (SELECT COUNT(*) FROM reposts WHERE post_id = p.id) AS rp_count, -- 追加
            (SELECT COUNT(*) FROM posts AS reply WHERE reply.reply_post_id = p.id) AS reply_count, -- 追加
            NULL AS repost_by,
            p.created AS sort_date
        FROM favorites f
        JOIN posts p ON f.post_id = p.id
        JOIN members m ON p.member_id = m.id
        WHERE f.member_id = ?
        GROUP BY p.id
        ORDER BY p.created DESC
        LIMIT ?, 5
    ');

    $posts->bindValue(1,$member['id'],PDO::PARAM_INT);
    $posts->bindValue(2,$start,PDO::PARAM_INT);
    $posts->execute();
}

// 会員登録日から今日までの日数を計算
$start_date = new DateTime($member['created']);
$today = new DateTime();
$interval = $start_date->diff($today);
$days_spent = $interval->days + 1; // 登録当日を1日目とする

// 日付が変わっていたらリセットする処理も mypage.php に入れておくと完璧です
$today_str = date('Y-m-d');
$reset_stmt = $db->prepare("
    UPDATE ranking
    SET post_count = 0, last_post_date = ?
    WHERE member_id = ? AND last_post_date != ?
");
$reset_stmt->execute(array($today_str, $member['id'], $today_str));

// その後にデータを取得
$stmt = $db->prepare('SELECT * FROM ranking WHERE member_id=?');
$stmt->execute(array($member['id']));
$my_ranking_data = $stmt->fetch();

// 自分の順位を取得（自分よりポイントが高い人数 + 1）
$ranking_q = $db->prepare('SELECT COUNT(*) + 1 as rank FROM ranking WHERE points > (SELECT points FROM ranking WHERE member_id=?)');
$ranking_q->execute(array($member['id']));
$my_rank = $ranking_q->fetch();
?>

<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link rel="stylesheet" href="css/style.css">
<title>くもっち | Mypage</title>
</head>
<body>
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
<main>
    <div class="container">
    <!-- 画面左側 -->
    <div class="left">
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
    <a href="index.php">ホームへ戻る</a><br>
    <a href="ranking.php">ランキング</a><br>
    <a href="update.php">アカウント情報を変更する</a><br>
    <a href="logout.php">ログアウト</a><br>
    <a href="withdrawal.php">退会する</a>
    </div>
</div>

    <!-- 右側 -->
    <div class="right">
        <div class="mypage_top">
            <img src="member_picture/<?php echo htmlspecialchars($member['picture']); ?>" class="mypage_icon"><br>
            <strong><?php echo htmlspecialchars($member['name']); ?></strong>
        </div> <!-- mypage_top閉じタグ -->
        
        <div class="user-status-card" style="display: flex; gap: 20px; justify-content: center; margin: 20px 0; background: rgba(255,255,255,0.5); padding: 15px; border-radius: 20px;">
    <div class="status-item">
        <span style="display: block; font-size: 0.8em; color: #666;">くもっち</span>
        <span style="font-weight: bold; font-size: 1.2em;"><?php echo $days_spent; ?>日目</span>
    </div>
    <div class="status-item" style="border-left: 1px solid #ccc; padding-left: 20px;">
        <span style="display: block; font-size: 0.8em; color: #666;">ランキング</span>
        <span style="font-weight: bold; font-size: 1.2em; color: #f39c12;"><?php echo $my_rank['rank']; ?>位</span>
    </div>
    <div class="status-item" style="border-left: 1px solid #ccc; padding-left: 20px;">
        <span style="display: block; font-size: 0.8em; color: #666;">本日の投稿</span>
        <span style="font-weight: bold; font-size: 1.2em;"><?php echo htmlspecialchars($my_ranking_data['post_count'] ?? 0); ?> / 5</span>
    </div>
</div>
<div class="profile_tabs">
    <a href="mypage.php?tab=posts"
        class="<?php if($tab=='posts') echo 'active'; ?>">
        ポスト
    </a>
    <a href="mypage.php?tab=likes"
        class="<?php if($tab=='likes') echo 'active'; ?>">
        いいね
    </a>
</div>
<?php while ($post = $posts->fetch()): ?>
    <div class="msg <?php echo ($post['reply_post_id'] > 0) ? 'is-reply' : ''; ?>">
        <?php if (!empty($post['repost_by'])): ?>
            <p style="color: #4a90e2; font-size: 0.8em; margin-bottom: 5px;">
                <svg style="width:12px; height:12px; fill:currentColor;" viewBox="0 0 640 640">...</svg>
                <?php echo htmlspecialchars($post['repost_by'], ENT_QUOTES); ?> さんがリポストしました
            </p>
        <?php endif; ?>
        <img src="member_picture/<?php echo htmlspecialchars($post['picture']); ?>"  alt="" class="member-icon" />
        <span class="name"> ID：<?php echo htmlspecialchars($post['name'], ENT_QUOTES); ?> </span>
        <p class="post-text is-limit"><?php echo nl2br(htmlspecialchars($post['message'], ENT_QUOTES)); ?>
        </p>
        <button type="button" class="read-more-btn">続きを読む</button>
        <?php if (!empty($post['post_picture'])): ?>    
            <p>
                <img src="post_picture/<?php echo htmlspecialchars($post['post_picture']); ?>" width="200">
            </p>
        <?php endif; ?>
        <p class="day">
            <a  title="スレッドを見る"
                href="view.php?id=<?php echo htmlspecialchars($post['id'],ENT_QUOTES);?>"><?php echo time_elapsed_string($post['created']);?>
            </a>
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
            href="#" class="fav-link" data-id="<?php echo $post['id']; ?>" style="text-decoration: none;">
            <svg class="fa-heart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                <path d="M442.9 144C415.6 144 389.9 157.1 373.9 179.2L339.5 226.8C335 233 327.8 236.7 320.1 236.7C312.4 236.7 305.2 233 300.7 226.8L266.3 179.2C250.3 157.1 224.6 144 197.3 144C150.3 144 112.2 182.1 112.2 229.1C112.2 279 144.2 327.5 180.3 371.4C221.4 421.4 271.7 465.4 306.2 491.7C309.4 494.1 314.1 495.9 320.2 495.9C326.3 495.9 331 494.1 334.2 491.7C368.7 465.4 419 421.3 460.1 371.4C496.3 327.5 528.2 279 528.2 229.1C528.2 182.1 490.1 144 443.1 144zM335 151.1C360 116.5 400.2 96 442.9 96C516.4 96 576 155.6 576 229.1C576 297.7 533.1 358 496.9 401.9C452.8 455.5 399.6 502 363.1 529.8C350.8 539.2 335.6 543.9 320 543.9C304.4 543.9 289.2 539.2 276.9 529.8C240.4 502 187.2 455.5 143.1 402C106.9 358.1 64 297.7 64 229.1C64 155.6 123.6 96 197.1 96C239.8 96 280 116.5 305 151.1L320 171.8L335 151.1z"/>
            </svg>
            <span class="fav-count"><?php echo ($post['fav_count'] > 0) ? $post['fav_count'] : 0; ?></span>
        </a>
        <a title="この投稿にコメントする" href="view.php?id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>#reply-area" class="reply-link" style="text-decoration: none; margin-left: 10px;">
        <svg class="fa-reply-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
            <path d="M115.9 448.9C83.3 408.6 64 358.4 64 304C64 171.5 178.6 64 320 64C461.4 64 576 171.5 576 304C576 436.5 461.4 544 320 544C283.5 544 248.8 536.8 217.4 524L101 573.9C97.3 575.5 93.5 576 89.5 576C75.4 576 64 564.6 64 550.5C64 546.2 65.1 542 67.1 538.3L115.9 448.9zM153.2 418.7C165.4 433.8 167.3 454.8 158 471.9L140 505L198.5 479.9C210.3 474.8 223.7 474.7 235.6 479.6C261.3 490.1 289.8 496 319.9 496C437.7 496 527.9 407.2 527.9 304C527.9 200.8 437.8 112 320 112C202.2 112 112 200.8 112 304C112 346.8 127.1 386.4 153.2 418.7z"/>
        </svg>
        <span class="reply-count"><?php echo ($post['reply_count'] > 0) ? $post['reply_count'] : 0; ?></span>
        <span class="reply-label"></span>
        </a>
        <?php if ($tab == 'posts' && $_SESSION['id'] == $post['member_id'] && empty($post['repost_by'])): ?>
        <a  title="この投稿を削除する"
            href="delete.php?id=<?php echo htmlspecialchars($post['id']); ?>"
            class="delete-link"
            onclick="return confirm('本当に削除しますか');">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" class="action-icon delete-icon">
                <path d="M232.7 69.9L224 96L128 96C110.3 96 96 110.3 96 128C96 145.7 110.3 160 128 160L512 160C529.7 160 544 145.7 544 128C544 110.3 529.7 96 512 96L416 96L407.3 69.9C402.9 56.8 390.7 48 376.9 48L263.1 48C249.3 48 237.1 56.8 232.7 69.9zM512 208L128 208L149.1 531.1C150.7 556.4 171.7 576 197 576L443 576C468.3 576 489.3 556.4 490.9 531.1L512 208z"/>
            </svg>
        </a>
        </p>
        <?php endif; ?>
    </div>
    <hr>
<?php endwhile; ?>  
<?php if ($maxPage > 1): ?>
<div class="pagination">

<?php if ($page > 1): ?>
<a href="mypage.php?tab=<?php echo $tab; ?>&page=1">&laquo;</a>
<a href="mypage.php?tab=<?php echo $tab; ?>&page=<?php echo $page-1; ?>">&lsaquo;</a>
<?php endif; ?>

<?php
$range = 1;

$startPage = max(1, $page - $range);
$endPage   = min($maxPage, $page + $range);

if ($startPage > 1) {
    echo '<a href="mypage.php?tab='.$tab.'&page=1">1</a>';
    if ($startPage > 2) echo '<span class="dots">...</span>';
}

for ($i = $startPage; $i <= $endPage; $i++) {
    if ($i == $page) {
        echo '<span class="current">'.$i.'</span>';
    } else {
        echo '<a href="mypage.php?tab='.$tab.'&page='.$i.'">'.$i.'</a>';
    }
}

if ($endPage < $maxPage) {
    if ($endPage < $maxPage - 1) echo '<span class="dots">...</span>';
    echo '<a href="mypage.php?tab='.$tab.'&page='.$maxPage.'">'.$maxPage.'</a>';
}
?>

<?php if ($page < $maxPage): ?>
<a href="mypage.php?tab=<?php echo $tab; ?>&page=<?php echo $page+1; ?>">&rsaquo;</a>
<a href="mypage.php?tab=<?php echo $tab; ?>&page=<?php echo $maxPage; ?>">&raquo;</a>
<?php endif; ?>

</div>
<?php endif; ?>
</div>
</div> <!-- right閉じタグ -->
</main>
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
</body>
</html>