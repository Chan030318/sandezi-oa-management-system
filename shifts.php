<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

$error   = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // 删除班次
    if (($_POST['action'] ?? '') === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // 检查是否有排班正在使用此班次
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM schedules WHERE shift_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['total'] > 0) {
            $error = '此班次已有排班记录在使用，无法删除。请先删除相关排班后再试。';
        } else {
            $pdo->prepare("DELETE FROM shifts WHERE id = ?")->execute([$id]);
            header("Location: shifts.php");
            exit;
        }

    // 新增 / 编辑班次
    } else {
        $name        = trim($_POST['name'] ?? '');
        $start_time  = $_POST['start_time'] ?? '';
        $end_time    = $_POST['end_time'] ?? '';
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || $start_time === '' || $end_time === '') {
            $error = '班次名称、开始时间和结束时间为必填项。';
        } else {
            if (!empty($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE shifts SET name=?, start_time=?, end_time=?, description=? WHERE id=?");
                $stmt->execute([$name, $start_time, $end_time, $description, intval($_POST['id'])]);
                $message = '班次资料已更新';
            } else {
                $stmt = $pdo->prepare("INSERT INTO shifts (name, start_time, end_time, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $start_time, $end_time, $description]);
                $message = '班次新增成功';
            }
            header("Location: shifts.php");
            exit;
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $eid  = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ?");
    $stmt->execute([$eid]);
    $edit = $stmt->fetch();
}

$shifts = $pdo->query("SELECT * FROM shifts ORDER BY start_time ASC")->fetchAll();
?>

<div class="page-title">
    <h2>班次管理</h2>
    <p>设置早班、中班、晚班及直播班次模板。</p>
</div>

<?php if ($error): ?>
    <div class="alert"><?= safe($error) ?></div>
<?php endif; ?>
<?php if ($message): ?>
    <div class="success"><?= safe($message) ?></div>
<?php endif; ?>

<section class="panel">
    <h2><?= $edit ? '编辑班次' : '新增班次' ?></h2>
    <form method="POST" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= safe($edit['id'] ?? '') ?>">
        <label>班次名称 <span style="color:red">*</span>
            <input name="name" required value="<?= safe($edit['name'] ?? '') ?>">
        </label>
        <label>开始时间 <span style="color:red">*</span>
            <input name="start_time" type="time" required value="<?= safe($edit['start_time'] ?? '') ?>">
        </label>
        <label>结束时间 <span style="color:red">*</span>
            <input name="end_time" type="time" required value="<?= safe($edit['end_time'] ?? '') ?>">
        </label>
        <label>说明
            <input name="description" value="<?= safe($edit['description'] ?? '') ?>">
        </label>
        <button type="submit"><?= $edit ? '保存修改' : '新增班次' ?></button>
        <?php if ($edit): ?>
            <a class="btn-light" href="shifts.php">取消</a>
        <?php endif; ?>
    </form>
</section>

<section class="panel">
    <h2>班次列表</h2>
    <div style="overflow-x:auto;">
    <table>
        <tr><th>ID</th><th>班次名称</th><th>时间</th><th>说明</th><th>操作</th></tr>
        <?php foreach ($shifts as $s): ?>
        <tr>
            <td><?= safe($s['id']) ?></td>
            <td><span class="badge"><?= safe($s['name']) ?></span></td>
            <td><?= safe(substr($s['start_time'], 0, 5)) ?> - <?= safe(substr($s['end_time'], 0, 5)) ?></td>
            <td><?= safe($s['description']) ?></td>
            <td>
                <a href="?edit=<?= safe($s['id']) ?>">编辑</a>
                |
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('确定要删除「<?= safe($s['name']) ?>」班次吗？')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= safe($s['id']) ?>">
                    <button type="submit" class="btn-link">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
