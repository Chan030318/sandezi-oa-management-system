<?php
require_once __DIR__ . '/header.php';

$message = '';
$error   = '';

// 新增公告
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!has_role(['Admin', 'Manager'])) { http_response_code(403); die('没有权限'); }
    verify_csrf();
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '' || $content === '') {
        $error = '标题和内容不能为空。';
    } else {
        $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)")
            ->execute([$title, $content, $user['id']]);
        header("Location: announcements.php");
        exit;
    }
}

// 编辑公告
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!has_role(['Admin', 'Manager'])) { http_response_code(403); die('没有权限'); }
    verify_csrf();
    $id      = intval($_POST['id']      ?? 0);
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($id <= 0 || $title === '' || $content === '') {
        $error = '标题和内容不能为空。';
    } else {
        $pdo->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?")
            ->execute([$title, $content, $id]);
        header("Location: announcements.php");
        exit;
    }
}

// 删除公告
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!has_role(['Admin', 'Manager'])) { http_response_code(403); die('没有权限'); }
    verify_csrf();
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
    }
    header("Location: announcements.php");
    exit;
}

// 编辑模式：载入目标公告
$editAnnouncement = null;
if (isset($_GET['edit']) && has_role(['Admin', 'Manager'])) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editAnnouncement = $stmt->fetch() ?: null;
}

// 公告列表
$announcements = $pdo->query("
    SELECT a.*, u.name AS creator_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
")->fetchAll();
?>

<div class="page-title">
    <h2>公告中心</h2>
    <p>用于发布公司通知、排班提醒和内部公告。</p>
</div>

<?php if ($message): ?><div class="success"><?= safe($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<!-- 发布 / 编辑表单 -->
<?php if (has_role(['Admin', 'Manager'])): ?>
<section class="panel">
    <h2><?= $editAnnouncement ? '编辑公告' : '发布公告' ?></h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editAnnouncement ? 'edit' : 'add' ?>">
        <?php if ($editAnnouncement): ?>
            <input type="hidden" name="id" value="<?= intval($editAnnouncement['id']) ?>">
        <?php endif; ?>

        <label>标题 <span style="color:red">*</span></label>
        <input type="text" name="title" required
               placeholder="例如：本周排班已更新"
               value="<?= safe($editAnnouncement['title'] ?? '') ?>">

        <label style="margin-top:12px;">内容 <span style="color:red">*</span></label>
        <textarea name="content" required rows="5"
                  placeholder="请输入公告内容"><?= safe($editAnnouncement['content'] ?? '') ?></textarea>

        <div style="display:flex;gap:10px;margin-top:12px;">
            <button class="btn" type="submit"><?= $editAnnouncement ? '保存修改' : '发布公告' ?></button>
            <?php if ($editAnnouncement): ?>
                <a class="btn secondary" href="announcements.php">取消编辑</a>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<!-- 公告列表 -->
<section class="panel">
    <h2>公告列表</h2>
    <?php if ($announcements): ?>
    <div class="announcement-list">
        <?php foreach ($announcements as $a): ?>
            <div class="announcement-card">
                <div style="flex:1;min-width:0;">
                    <h3><?= safe($a['title']) ?></h3>
                    <p style="white-space:pre-wrap;word-break:break-word;"><?= safe($a['content']) ?></p>
                    <span style="font-size:13px;color:#888;">
                        发布人：<?= safe($a['creator_name'] ?? '—') ?> · <?= safe(substr($a['created_at'], 0, 16)) ?>
                    </span>
                </div>
                <?php if (has_role(['Admin', 'Manager'])): ?>
                <div style="display:flex;gap:10px;align-items:flex-start;flex-shrink:0;margin-top:4px;">
                    <a href="announcements.php?edit=<?= intval($a['id']) ?>"
                       style="font-size:13px;color:#5c7cfa;text-decoration:none;">编辑</a>
                    <form method="POST" onsubmit="return confirm('确定删除此公告？')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= intval($a['id']) ?>">
                        <button type="submit" class="btn-link" style="color:#9b1c1c;font-size:13px;">删除</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p style="color:#888;text-align:center;padding:32px 0;">暂无公告。</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
