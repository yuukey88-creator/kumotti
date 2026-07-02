<?php
session_start();
require('dbconnect.php');

// --- 1. ログインチェック ---
if(isset($_SESSION['id']) && $_SESSION['time'] + 60*60*24*14 > time()){
    $_SESSION['time'] = time();
    // ログイン中のユーザー情報を取得
    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    header('Location: login.php'); exit();
}
$my_id = $_SESSION['id'];
$today = date('Y-m-d');
// 日付が変わっていたら投稿数をリセットする
$reset_stmt = $db->prepare("
    UPDATE ranking
    SET post_count = 0, last_post_date = ?
    WHERE member_id = ? AND last_post_date != ?
");
$reset_stmt->execute(array($today, $my_id, $today));
// --- 2. 全ランキングデータの取得 ---
$ranking_all = $db->query("
    SELECT r.*, m.name,
           (r.member_id = $my_id) AS is_me
    FROM ranking r
    LEFT JOIN members m ON r.member_id = m.id
    ORDER BY r.points DESC,
             is_me DESC,
             r.post_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// --- 自分のデータを探す ---
$my_ranking_data = null;
foreach ($ranking_all as $r) {
    if (isset($r['member_id']) && $r['member_id'] == $my_id) {
        $my_ranking_data = $r;
        break;
    }
}

// ★ もし自分のデータがなければ、新規作成して再取得
if (!$my_ranking_data) {
    // 1. 過去の全投稿数をカウントして初期ポイントを計算
    $stmt = $db->prepare('
    SELECT
        (SELECT COUNT(*) FROM posts WHERE member_id=?) as post_count,
        (SELECT COUNT(*) FROM reposts WHERE member_id=?) as repost_count
    ');
    $stmt->execute(array($my_id, $my_id));
    $data = $stmt->fetch();
    $initial_points = ($data['post_count'] * 5) + ($data['repost_count'] * 2);
   
    // 2. ランキングに新規作成
    $stmt = $db->prepare('INSERT INTO ranking (member_id, points, post_count, repost_count, last_post_date) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(array($my_id, $initial_points, 0, 0, '2000-01-01'));
   
    // 3. 【修正箇所】ランキングデータ全体を最新の状態に更新する
    $ranking_all = $db->query("
        SELECT r.*, m.name,
               (r.member_id = $my_id) AS is_me
        FROM ranking r
        LEFT JOIN members m ON r.member_id = m.id
        ORDER BY r.points DESC,
                 is_me DESC,
                 r.post_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. 改めて自分のデータを再取得
    $stmt = $db->prepare('SELECT r.*, m.name FROM ranking r LEFT JOIN members m ON r.member_id = m.id WHERE r.member_id=?');
    $stmt->execute(array($my_id));
    $my_ranking_data = $stmt->fetch();
}

// 表示用変数
$last_points = null;
$current_rank = 1;
?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ランキング</title>
<style>
    body { 
        font-family: sans-serif;
        background: #f4f7f9;
        padding: 20px;
    }
    .card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        width: 400px;
        max-width:100%;
    }
    .my-status { background: #e8f5fe; padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: center; }
    .status-item {
        display: flex;
        justify-content: space-between;
        padding: 12px;
        border: 1px solid #e1e8ed;
        border-radius: 8px;
        margin-bottom: 8px;
        background: #fafafa;
        color: #555;
    }
    .status-label { font-weight: bold; }
    .status-count { font-weight: bold; color: #1da1f2; }
    a.back-link { display: inline-block; margin-top: 15px; color: #1da1f2; text-decoration: none; font-size: 0.9em; }
    .my-rank { background-color: #fff9e6; font-weight: bold; }
    .ranking-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
    }
    .ranking-points {
        width: 100px;
        text-align: right;
        font-weight: bold;
        color: #1da1f2;
        font-family: monospace;
    }

    .ranking-wrap{
        display:flex;
        gap:20px;
        justify-content:center;
    }

    @media screen and (max-width:820px){
    .ranking-wrap{
        flex-direction:column;
        align-items:center;
    }
}
</style>
</head>
<body>
<div class="ranking-wrap">
<div class="card">
    <a href="index.php" class="back-link">← タイムラインに戻る</a>
    <h2>アクティブ・ポイント</h2>
    <?php if ($my_ranking_data): ?>
        <div class="my-status">
            <strong><?= htmlspecialchars($my_ranking_data['name']) ?></strong> さんのスコア<br>
            <span style="font-size: 1.8em; color: #1da1f2; font-weight: bold;"><?= $my_ranking_data['points'] ?> <small style="font-size: 0.5em;">ポイント</small></span>
        </div>
        <p style="font-size: 0.8em; color: #888; text-align: center;">※タイムラインでの活動でポイントを獲得できます</p>
        <h3>本日の実績状況</h3>
        <div class="status-item <?= ($my_ranking_data['post_count'] >= 5) ? 'is-full' : '' ?>">
            <span class="status-label">📝 本日の投稿件数</span>
            <span class="status-count"><?= $my_ranking_data['post_count'] ?> / 5</span>
        </div>
    <?php else: ?>
        <p>データがありません</p>
    <?php endif; ?>
    <p style="font-size: 0.8em; color: #888; text-align: center;">※投稿によるポイント獲得は1日5件までとなっています</p>
    <p style="font-size: 0.8em; color: #888; text-align: center;">※ポイント獲得の上限に達しても、投稿することは可能です</p>
    <h3 style="font-size: 1.1em; margin-top: 20px;">ポイント獲得ルール</h3>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.9em;">
        <tr style="background: #f8f8f8;">
            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">アクション</th>
            <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">獲得pt</th>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">新規投稿</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: #1da1f2;">5pt</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">リツイート獲得</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: #1da1f2;">2pt</td>
        </tr>
    </table>
    <p style="font-size: 0.8em; color: #888; text-align: center;">※投稿やリツイートを取消すると、ポイントは減少します</p>
</div>
<?php
    // --- 順位計算の準備 ---
    $rank_display = 1; // 表示用の順位
    $last_points = -1; // 前の人のポイント（初期値）


    // 自分の順位（ランク）を先に特定しておく
    $my_rank = 0;
    foreach ($ranking_all as $i => $r) {
        if ($i > 0 && $r['points'] < $ranking_all[$i-1]['points']) {
            $rank_display = $i + 1;
        }
        if ($r['member_id'] == $my_id) {
            $my_rank = $rank_display;
            break;
        }
    }
?>

<div class="card">
    <h2>ランキング TOP10</h2>
    <?php
    $rank_display = 1;
    $last_points = -1;


    foreach ($ranking_all as $i => $r):
        // ポイントが変わったら順位を更新（同点なら順位はそのまま）
        if ($r['points'] < $last_points) {
            $rank_display = $i + 1;
        }
        $last_points = $r['points'];
       
        // --- 表示判定 ---
        // 10位以内、または自分自身を表示
        if ($rank_display <= 10 || $r['member_id'] == $my_id):
           
            // 11位以降で、自分の行を表示する直前に区切り線を入れる
            if ($rank_display > 10 && $r['member_id'] == $my_id && $i > 0 && $ranking_all[$i-1]['member_id'] != $my_id) {
                echo '<div class="ranking-separator">...</div>';
            }
    ?>
    <div class="ranking-item <?= ($r['member_id'] == $my_id) ? 'my-rank' : '' ?>">
    <span><?= $rank_display ?>位: <?= htmlspecialchars($r['name'] ?? '退会済みユーザー', ENT_QUOTES); ?></span>
   
    <span class="ranking-points">
    <?= htmlspecialchars($r['points'], ENT_QUOTES); ?> pt
    </span>
</div>
    <?php
        endif;
    endforeach;
    ?>
</div>
</div>
</body>
</html>
