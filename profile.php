<?php
require_once __DIR__ . '/header.php';
// 所有已登录用户均可访问

$message = '';
$error   = '';
$me      = current_user();
$uid     = intval($me['id']);

// 读取最新用户资料（含关联员工）
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           e.position, e.phone, e.department_id,
           d.name AS department_name,
           e.id   AS employee_id
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE u.id = ?
");
$stmt->execute([$uid]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $new_name  = trim($_POST['name']  ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    if ($new_name === '') {
        $error = '姓名不能为空。';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确。';
    } else {
        // 检查 email 是否被其他用户占用
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$new_email, $uid]);
        if ($chk->fetch()) {
            $error = '该邮箱已被其他账号使用，请换一个。';
        } else {
            // 更新 users 表
            $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")
                ->execute([$new_name, $new_email, $uid]);

            // 同步 employees 表的 phone（若有关联员工）
            if ($profile['employee_id']) {
                $pdo->prepare("UPDATE employees SET phone = ? WHERE id = ?")
                    ->execute([$new_phone, $profile['employee_id']]);
            }

            // 同步 Session
            $_SESSION['user']['name']  = $new_name;
            $_SESSION['user']['email'] = $new_email;

            // 刷新本地变量
            $profile['name']  = $new_name;
            $profile['email'] = $new_email;
            $profile['phone'] = $new_phone;
            $me = current_user();

            $message = '个人资料已更新。';
        }
    }
}

$role_labels = ['Admin' => '管理员', 'Manager' => '主管', 'Employee' => '员工'];
?>

<div class="page-title">
    <h2>个人资料</h2>
    <p>查看与修改您的基本资料。</p>
</div>

<?php if ($message): ?>
    <div class="success"><?= safe($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert"><?= safe($error) ?></div>
<?php endif; ?>

<!-- 只读信息卡 -->
<section class="panel">
    <h2>账号信息</h2>
    <div class="form-grid">
        <div>
            <label>系统角色</label>
            <p style="font-weight:bold;margin:6px 0;">
                <?= safe($role_labels[$profile['role']] ?? $profile['role']) ?>
            </p>
        </div>
        <div>
            <label>所属部门</label>
            <p style="margin:6px 0;"><?= safe($profile['department_name'] ?? '—') ?></p>
        </div>
        <div>
            <label>职位</label>
            <p style="margin:6px 0;"><?= safe($profile['position'] ?? '—') ?></p>
        </div>
        <div>
            <label>账号建立时间</label>
            <p style="margin:6px 0;"><?= safe(substr($profile['created_at'], 0, 10)) ?></p>
        </div>
    </div>
</section>

<!-- 可编辑区 -->
<section class="panel">
    <h2>修改资料</h2>
    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label>姓名 <span style="color:red">*</span></label>
                <input type="text" name="name" required
                       value="<?= safe($profile['name']) ?>">
            </div>
            <div>
                <label>Email <span style="color:red">*</span></label>
                <input type="email" name="email" required
                       value="<?= safe($profile['email']) ?>">
            </div>
            <div>
                <label>电话
                    <?php if (!$profile['employee_id']): ?>
                        <small style="color:#aaa;font-weight:normal;">（需绑定员工才能修改）</small>
                    <?php endif; ?>
                </label>
                <input type="text" name="phone"
                       value="<?= safe($profile['phone'] ?? '') ?>"
                       <?= !$profile['employee_id'] ? 'disabled' : '' ?>
                       placeholder="例如：013-12345678">
            </div>
        </div>
        <button class="btn" type="submit" style="margin-top:12px;">保存修改</button>
    </form>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
