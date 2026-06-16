<?php
require_once __DIR__ . '/header.php';
require_role(['Admin']);

$message = '';
$error = '';
$me = current_user();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: users.php");
    exit;
}

// 读取目标用户
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$target = $stmt->fetch();
if (!$target) {
    header("Location: users.php");
    exit;
}

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 修改资料（角色 / 状态 / 关联员工）
    if ($action === 'edit') {
        $role        = $_POST['role'] ?? $target['role'];
        $status      = $_POST['status'] ?? $target['status'];
        $employee_id = intval($_POST['employee_id'] ?? 0) ?: null;

        $allowed_roles = ['Admin', 'Manager', 'Employee'];
        if (!in_array($role, $allowed_roles)) {
            $error = '无效的角色';
        } elseif ($status === 'inactive' && intval($target['id']) === intval($me['id'])) {
            $error = '不能停用当前登录的自己';
        } else {
            $upd = $pdo->prepare("
                UPDATE users SET role = ?, status = ?, employee_id = ? WHERE id = ?
            ");
            $upd->execute([$role, $status, $employee_id, $id]);
            // 刷新本地变量
            $target['role']        = $role;
            $target['status']      = $status;
            $target['employee_id'] = $employee_id;
            $message = '用户资料已更新';
        }
    }

    // 重设密码
    if ($action === 'reset_password') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm      = $_POST['confirm_password'] ?? '';
        if (!$new_password) {
            // 空白则跳过（不改密码）
            $message = '密码未填写，保持不变';
        } elseif (strlen($new_password) < 6) {
            $error = '密码至少 6 位';
        } elseif ($new_password !== $confirm) {
            $error = '两次密码不一致';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $upd  = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->execute([$hash, $id]);
            $message = '密码已重设';
        }
    }
}

// 所有员工供选择
$employees_all = $pdo->query("
    SELECT e.id, e.name, e.position, d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.name ASC
")->fetchAll();

// 已关联的 employee_id（排除当前用户自身）
$linked_ids_stmt = $pdo->prepare("SELECT employee_id FROM users WHERE employee_id IS NOT NULL AND id != ?");
$linked_ids_stmt->execute([$id]);
$linked_ids = $linked_ids_stmt->fetchAll(\PDO::FETCH_COLUMN);
?>

<div class="page-title">
    <h2>编辑用户</h2>
    <p>修改用户角色、状态、关联员工，或重设密码。</p>
</div>

<?php if ($message): ?>
    <div class="panel" style="color:#166534;font-weight:bold;"><?= safe($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="panel" style="color:#9b1c1c;font-weight:bold;"><?= safe($error) ?></div>
<?php endif; ?>

<!-- 基本资料区 -->
<section class="panel">
    <h2>基本资料 — <?= safe($target['name']) ?> <small style="color:#888;font-size:14px"><?= safe($target['email']) ?></small></h2>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit">

        <div class="form-grid">
            <div>
                <label>角色</label>
                <select name="role">
                    <?php foreach (['Employee'=>'Employee — 普通员工','Manager'=>'Manager — 主管','Admin'=>'Admin — 管理员'] as $val=>$label): ?>
                        <option value="<?= $val ?>" <?= $target['role']===$val?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>状态</label>
                <select name="status" <?= intval($target['id'])===intval($me['id'])?'disabled title="不能停用自己"':'' ?>>
                    <option value="active"   <?= $target['status']==='active'  ?'selected':''?>>启用</option>
                    <option value="inactive" <?= $target['status']==='inactive'?'selected':''?>>停用</option>
                </select>
                <?php if (intval($target['id']) === intval($me['id'])): ?>
                    <input type="hidden" name="status" value="active">
                    <small style="color:#888">不能停用当前登录的自己</small>
                <?php endif; ?>
            </div>

            <div style="grid-column:1/-1">
                <label>关联员工（可选）</label>
                <select name="employee_id">
                    <option value="">— 不关联员工 —</option>
                    <?php foreach ($employees_all as $e): ?>
                        <?php $already = in_array($e['id'], $linked_ids); ?>
                        <option value="<?= safe($e['id']) ?>"
                            <?= intval($target['employee_id'])===$e['id'] ? 'selected' : '' ?>
                            <?= ($already && intval($target['employee_id'])!==$e['id']) ? 'disabled' : '' ?>>
                            <?= safe($e['name']) ?>（<?= safe($e['department_name'] ?? '无部门') ?> · <?= safe($e['position']) ?>）
                            <?= ($already && intval($target['employee_id'])!==$e['id']) ? '[已关联]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button class="btn" type="submit">保存修改</button>
        <a class="btn secondary" href="users.php">返回列表</a>
    </form>
</section>

<!-- 重设密码区 -->
<section class="panel">
    <h2>重设密码</h2>
    <p style="color:#888;font-size:14px">密码留空则不修改。</p>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <div class="form-grid">
            <div>
                <label>新密码（至少 6 位）</label>
                <input type="password" name="new_password" placeholder="留空则不修改">
            </div>
            <div>
                <label>确认新密码</label>
                <input type="password" name="confirm_password" placeholder="再输入一次">
            </div>
        </div>
        <button class="btn" type="submit">重设密码</button>
    </form>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
