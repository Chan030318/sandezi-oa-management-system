<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if (!empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE departments SET name=?, description=? WHERE id=?");
        $stmt->execute([$name, $description, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
    }

    header("Location: departments.php");
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM employees WHERE department_id=?");
    $stmt->execute([$_GET['delete']]);
    if ($stmt->fetch()['total'] > 0) {
        $error = "该部门已有员工，不能删除。";
    } else {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id=?");
        $stmt->execute([$_GET['delete']]);
        header("Location: departments.php");
        exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}


$departments = $pdo->query("
    SELECT d.*, COUNT(e.id) AS employee_count
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id
    GROUP BY d.id
    ORDER BY d.id ASC
")->fetchAll();
?>

<div class="page-title"><h2>部门管理</h2><p>维护公司部门资料，后续可用于权限和排班范围控制。</p></div>
<?php if ($error): ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<section class="panel">
    <h2><?= $edit ? '编辑部门' : '新增部门' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= safe($edit['id'] ?? '') ?>">
        <label>部门名称<input name="name" required value="<?= safe($edit['name'] ?? '') ?>"></label>
        <label>说明<input name="description" value="<?= safe($edit['description'] ?? '') ?>"></label>
        <button type="submit"><?= $edit ? '保存修改' : '新增部门' ?></button>
    </form>
</section>

<section class="panel">
    <h2>部门列表</h2>
    <table>
        <tr><th>ID</th><th>部门名称</th><th>说明</th><th>员工数量</th><th>操作</th></tr>
        <?php foreach ($departments as $d): ?>
        <tr>
            <td><?= safe($d['id']) ?></td>
            <td><?= safe($d['name']) ?></td>
            <td><?= safe($d['description']) ?></td>
            <td><?= safe($d['employee_count']) ?></td>
            <td>
                <a href="?edit=<?= safe($d['id']) ?>">编辑</a>
                <a href="?delete=<?= safe($d['id']) ?>" onclick="return confirm('确定删除？')">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
