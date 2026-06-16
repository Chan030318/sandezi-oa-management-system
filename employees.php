<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

$message = "";

// 新增员工
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $stmt = $pdo->prepare("
        INSERT INTO employees (name, department_id, position, phone, email, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST['name'],
        $_POST['department_id'],
        $_POST['position'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['status']
    ]);

    $message = "员工新增成功";
}

// 编辑员工
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $stmt = $pdo->prepare("
        UPDATE employees
        SET name = ?, department_id = ?, position = ?, phone = ?, email = ?, status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['name'],
        $_POST['department_id'],
        $_POST['position'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['status'],
        $_POST['id']
    ]);

    $message = "员工资料已更新";
}

// 删除员工
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$_GET['delete']]);

    $message = "员工已删除";
}

// 部门资料
$departments = $pdo->query("SELECT * FROM departments ORDER BY id ASC")->fetchAll();

// 员工资料
$employees = $pdo->query("
    SELECT e.*, d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY e.id DESC
")->fetchAll();

// 编辑资料
$editEmployee = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editEmployee = $stmt->fetch();
}
?>

<div class="page-title">
    <h2>员工管理</h2>
    <p>管理员工资料、部门、岗位与状态。</p>
</div>

<?php if ($message): ?>
    <div class="panel" style="color:#9b1c1c;font-weight:bold;">
        <?= safe($message) ?>
    </div>
<?php endif; ?>

<section class="panel">
    <h2><?= $editEmployee ? '编辑员工' : '新增员工' ?></h2>

    <form method="POST">
        <input type="hidden" name="action" value="<?= $editEmployee ? 'edit' : 'add' ?>">

        <?php if ($editEmployee): ?>
            <input type="hidden" name="id" value="<?= safe($editEmployee['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>姓名</label>
                <input type="text" name="name" required value="<?= safe($editEmployee['name'] ?? '') ?>">
            </div>

            <div>
                <label>部门</label>
                <select name="department_id" required>
                    <option value="">请选择部门</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= safe($d['id']) ?>"
                            <?= (($editEmployee['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                            <?= safe($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>岗位</label>
                <input type="text" name="position" value="<?= safe($editEmployee['position'] ?? '') ?>">
            </div>

            <div>
                <label>电话</label>
                <input type="text" name="phone" value="<?= safe($editEmployee['phone'] ?? '') ?>">
            </div>

            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= safe($editEmployee['email'] ?? '') ?>">
            </div>

            <div>
                <label>状态</label>
                <select name="status">
                    <option value="active" <?= (($editEmployee['status'] ?? '') == 'active') ? 'selected' : '' ?>>在职</option>
                    <option value="inactive" <?= (($editEmployee['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>离职</option>
                </select>
            </div>
        </div>

        <button class="btn" type="submit">
            <?= $editEmployee ? '保存修改' : '新增员工' ?>
        </button>

        <?php if ($editEmployee): ?>
            <a class="btn secondary" href="employees.php">取消编辑</a>
        <?php endif; ?>
    </form>
</section>

<section class="panel">
    <h2>员工列表</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>姓名</th>
            <th>部门</th>
            <th>岗位</th>
            <th>电话</th>
            <th>Email</th>
            <th>状态</th>
            <th>操作</th>
        </tr>

        <?php foreach ($employees as $e): ?>
            <tr>
                <td><?= safe($e['id']) ?></td>
                <td><?= safe($e['name']) ?></td>
                <td><?= safe($e['department_name'] ?? '-') ?></td>
                <td><?= safe($e['position']) ?></td>
                <td><?= safe($e['phone']) ?></td>
                <td><?= safe($e['email']) ?></td>
                <td>
                    <span class="badge">
                        <?= $e['status'] === 'active' ? '在职' : '离职' ?>
                    </span>
                </td>
                <td>
                    <a href="employees.php?edit=<?= safe($e['id']) ?>">编辑</a>
                    |
                    <a href="employees.php?delete=<?= safe($e['id']) ?>"
                       onclick="return confirm('确定要删除这个员工吗？')">
                       删除
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>