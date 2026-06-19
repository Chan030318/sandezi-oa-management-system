<?php
require_once __DIR__ . '/header.php';
require_role(['Admin']);

$message = '';
$error = '';

// 新增用户
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $role        = $_POST['role'] ?? 'Employee';
    $employee_id = intval($_POST['employee_id'] ?? 0) ?: null;
    $status      = $_POST['status'] ?? 'active';

    $allowed_roles = ['Admin', 'Manager', 'Employee'];
    if (!$name || !$email || !$password) {
        $error = '姓名、邮箱、密码为必填项';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } elseif (!in_array($role, $allowed_roles)) {
        $error = '无效的角色';
    } elseif (strlen($password) < 6) {
        $error = '密码至少 6 位';
    } else {
        // 检查 email 重复
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = '该邮箱已被注册，请使用其他邮箱';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (employee_id, name, email, password_hash, role, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$employee_id, $name, $email, $hash, $role, $status]);
            write_audit_log('用户管理', '新增用户', "新增用户：{$name}（{$email}，角色：{$role}）");
            $message = '用户新增成功';
        }
    }
}

// 快速停用 / 启用（列表内操作）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $target_id = intval($_POST['id'] ?? 0);
    $me = current_user();
    if ($target_id === intval($me['id'])) {
        $error = '不能停用当前登录的自己';
    } else {
        $row = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $row->execute([$target_id]);
        $current_status = $row->fetchColumn();
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        $upd = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $upd->execute([$new_status, $target_id]);
        $log_action = $new_status === 'active' ? '启用用户' : '停用用户';
        write_audit_log('用户管理', $log_action, "{$log_action}：用户 ID {$target_id}");
        $message = $new_status === 'active' ? '用户已启用' : '用户已停用';
    }
}

// 获取所有未关联账号的员工（供新增时选择）
$employees_all = $pdo->query("
    SELECT e.id, e.name, e.position, d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.name ASC
")->fetchAll();

// 已关联的 employee_id（避免一个员工绑多个账号）
$linked_ids = $pdo->query("SELECT employee_id FROM users WHERE employee_id IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN);

// 用户列表
$users = $pdo->query("
    SELECT u.*, e.name AS employee_name, e.position, d.name AS department_name
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY u.id ASC
")->fetchAll();
?>

<div class="page-title">
    <h2>用户管理</h2>
    <p>管理 OA 系统登录账号，包含角色与权限设置。</p>
</div>

<?php if ($message): ?>
    <div class="panel" style="color:#166534;font-weight:bold;"><?= safe($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="panel" style="color:#9b1c1c;font-weight:bold;"><?= safe($error) ?></div>
<?php endif; ?>

<!-- 新增用户表单 -->
<section class="panel">
    <h2>新增用户</h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div>
                <label>姓名 <span style="color:red">*</span></label>
                <input type="text" name="name" required placeholder="显示名称">
            </div>
            <div>
                <label>邮箱 <span style="color:red">*</span></label>
                <input type="email" name="email" required placeholder="login@sandezi.com">
            </div>
            <div>
                <label>密码 <span style="color:red">*</span>（至少 6 位）</label>
                <input type="password" name="password" required placeholder="初始密码">
            </div>
            <div>
                <label>角色</label>
                <select name="role">
                    <option value="Employee">Employee — 普通员工</option>
                    <option value="Manager">Manager — 主管</option>
                    <option value="Admin">Admin — 管理员</option>
                </select>
            </div>
            <div>
                <label>状态</label>
                <select name="status">
                    <option value="active">启用</option>
                    <option value="inactive">停用</option>
                </select>
            </div>
            <div>
                <label>关联员工（可选）</label>
                <select name="employee_id">
                    <option value="">— 不关联员工 —</option>
                    <?php foreach ($employees_all as $e): ?>
                        <?php $already = in_array($e['id'], $linked_ids); ?>
                        <option value="<?= safe($e['id']) ?>" <?= $already ? 'disabled' : '' ?>>
                            <?= safe($e['name']) ?>
                            （<?= safe($e['department_name'] ?? '无部门') ?> · <?= safe($e['position']) ?>）
                            <?= $already ? '[已关联]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn" type="submit">新增用户</button>
    </form>
</section>

<!-- 用户列表 -->
<section class="panel">
    <h2>用户列表</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>姓名</th>
            <th>邮箱</th>
            <th>角色</th>
            <th>关联员工</th>
            <th>状态</th>
            <th>创建时间</th>
            <th>操作</th>
        </tr>
        <?php foreach ($users as $u):
            $is_me = (intval($u['id']) === intval(current_user()['id']));
        ?>
        <tr>
            <td><?= safe($u['id']) ?></td>
            <td><?= safe($u['name']) ?> <?= $is_me ? '<span style="color:#315f3b;font-size:12px">(我)</span>' : '' ?></td>
            <td><?= safe($u['email']) ?></td>
            <td>
                <?php
                $role_labels = ['Admin'=>'管理员','Manager'=>'主管','Employee'=>'员工'];
                echo safe($role_labels[$u['role']] ?? $u['role']);
                ?>
            </td>
            <td>
                <?= $u['employee_name'] ? safe($u['employee_name']) . '<br><small>' . safe($u['department_name'] ?? '') . '</small>' : '<span style="color:#aaa">—</span>' ?>
            </td>
            <td>
                <span class="badge" style="background:<?= $u['status']==='active'?'#dcfce7;color:#166534':'#fee2e2;color:#9b1c1c' ?>">
                    <?= $u['status'] === 'active' ? '启用' : '停用' ?>
                </span>
            </td>
            <td><?= safe(substr($u['created_at'], 0, 10)) ?></td>
            <td>
                <a href="user_edit.php?id=<?= safe($u['id']) ?>">编辑</a>
                <?php if (!$is_me): ?>
                    |
                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定要<?= $u['status']==='active'?'停用':'启用' ?>此用户吗？')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?= safe($u['id']) ?>">
                        <button type="submit" class="btn-link">
                            <?= $u['status'] === 'active' ? '停用' : '启用' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
