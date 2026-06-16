<?php
require_once __DIR__ . '/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_role(['Admin', 'Manager'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$title, $content, $user['id']]);

    header("Location: announcements.php");
    exit;
}

if (isset($_GET['delete']) && has_role(['Admin', 'Manager'])) {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: announcements.php");
    exit;
}

$announcements = $pdo->query("
    SELECT a.*, u.name AS creator_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
")->fetchAll();
?>

<div class="page-title"><h2>公告中心</h2><p>用于发布公司通知、排班提醒和内部公告。</p></div>

<?php if (has_role(['Admin', 'Manager'])): ?>
<section class="panel">
    <h2>发布公告</h2>
    <form method="post">
        <label>标题</label>
        <input name="title" required placeholder="例如：本周排班已更新">

        <label>内容</label>
        <textarea name="content" required placeholder="请输入公告内容"></textarea>

        <button type="submit">发布公告</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <h2>公告列表</h2>
    <div class="announcement-list">
        <?php foreach ($announcements as $a): ?>
            <div class="announcement-card">
                <div>
                    <h3><?= safe($a['title']) ?></h3>
                    <p><?= nl2br(safe($a['content'])) ?></p>
                    <span>发布人：<?= safe($a['creator_name'] ?? '-') ?> · <?= safe($a['created_at']) ?></span>
                </div>
                <?php if (has_role(['Admin', 'Manager'])): ?>
                    <a href="?delete=<?= safe($a['id']) ?>" onclick="return confirm('确定删除？')">删除</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
