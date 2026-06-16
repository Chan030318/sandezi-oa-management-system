<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $description = trim($_POST['description']);

    if (!empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE shifts SET name=?, start_time=?, end_time=?, description=? WHERE id=?");
        $stmt->execute([$name, $start_time, $end_time, $description, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO shifts (name, start_time, end_time, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $start_time, $end_time, $description]);
    }

    header("Location: shifts.php");
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM shifts WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: shifts.php");
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$shifts = $pdo->query("SELECT * FROM shifts ORDER BY start_time ASC")->fetchAll();
?>

<div class="page-title"><h2>班次管理</h2><p>设置早班、中班、晚班及直播班次模板。</p></div>

<section class="panel">
    <h2><?= $edit ? '编辑班次' : '新增班次' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= safe($edit['id'] ?? '') ?>">
        <label>班次名称<input name="name" required value="<?= safe($edit['name'] ?? '') ?>"></label>
        <label>开始时间<input name="start_time" type="time" required value="<?= safe($edit['start_time'] ?? '') ?>"></label>
        <label>结束时间<input name="end_time" type="time" required value="<?= safe($edit['end_time'] ?? '') ?>"></label>
        <label>说明<input name="description" value="<?= safe($edit['description'] ?? '') ?>"></label>
        <button type="submit"><?= $edit ? '保存修改' : '新增班次' ?></button>
    </form>
</section>

<section class="panel">
    <h2>班次列表</h2>
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
                <a href="?delete=<?= safe($s['id']) ?>" onclick="return confirm('确定删除？')">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
