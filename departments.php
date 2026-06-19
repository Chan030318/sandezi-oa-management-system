<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

$error   = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // 删除部门
    if (($_POST['action'] ?? '') === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM employees WHERE department_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['total'] > 0) {
            $error = '该部门已有员工，不能删除。';
        } else {
            $del_row = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $del_row->execute([$id]);
            $del_name = $del_row->fetchColumn() ?: "ID {$id}";
            $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
            write_audit_log('部门管理', '删除部门', "删除部门：{$del_name}（ID {$id}）");
            header("Location: departments.php");
            exit;
        }

    // 新增 / 编辑部门
    } else {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = '部门名称不能为空。';
        } else {
            if (!empty($_POST['id'])) {
                $dept_id = intval($_POST['id']);
                $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $dept_id]);
                write_audit_log('部门管理', '编辑部门', "编辑部门：{$name}（ID {$dept_id}）");
                $message = '部门资料已更新';
            } else {
                $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                write_audit_log('部门管理', '新增部门', "新增部门：{$name}");
                $message = '部门新增成功';
            }
            header("Location: departments.php");
            exit;
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $eid  = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$eid]);
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

<div class="page-title">
    <h2>部门管理</h2>
    <p>维护公司部门资料，后续可用于权限和排班范围控制。</p>
</div>

<?php if ($error): ?>
    <div class="alert"><?= safe($error) ?></div>
<?php endif; ?>
<?php if ($message): ?>
    <div class="success"><?= safe($message) ?></div>
<?php endif; ?>

<section class="panel">
    <h2><?= $edit ? '编辑部门' : '新增部门' ?></h2>
    <form method="POST" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= safe($edit['id'] ?? '') ?>">
        <label>部门名称 <span style="color:red">*</span>
            <input name="name" required value="<?= safe($edit['name'] ?? '') ?>">
        </label>
        <label>说明
            <input name="description" value="<?= safe($edit['description'] ?? '') ?>">
        </label>
        <button type="submit"><?= $edit ? '保存修改' : '新增部门' ?></button>
        <?php if ($edit): ?>
            <a class="btn-light" href="departments.php">取消</a>
        <?php endif; ?>
    </form>
</section>

<section class="panel">
    <h2>部门列表</h2>
    <div style="overflow-x:auto;">
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
                |
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('确定要删除「<?= safe($d['name']) ?>」吗？')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= safe($d['id']) ?>">
                    <button type="submit" class="btn-link">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
