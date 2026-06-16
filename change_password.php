<?php
require_once __DIR__ . '/header.php';
// 所有已登录用户均可访问，require_login() 已在 header.php 内执行

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current_password  = $_POST['current_password']  ?? '';
    $new_password      = $_POST['new_password']      ?? '';
    $confirm_password  = $_POST['confirm_password']  ?? '';
    $user_id           = intval(current_user()['id']);

    // 取当前密码 hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current_password, $row['password_hash'])) {
        $error = '当前密码不正确';
    } elseif (strlen($new_password) < 8) {
        $error = '新密码至少 8 位';
    } elseif ($new_password !== $confirm_password) {
        $error = '新密码与确认密码不一致';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd  = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $upd->execute([$hash, $user_id]);
        $message = '密码已成功修改，请妥善保管新密码。';
    }
}
?>

<div class="page-title">
    <h2>修改密码</h2>
    <p>修改您的登录密码，新密码至少 8 位。</p>
</div>

<?php if ($message): ?>
    <div class="panel" style="color:#166534;font-weight:bold;"><?= safe($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="panel" style="color:#9b1c1c;font-weight:bold;"><?= safe($error) ?></div>
<?php endif; ?>

<section class="panel">
    <h2>修改密码 — <?= safe(current_user()['name']) ?></h2>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div style="grid-column:1/-1">
                <label>当前密码 <span style="color:red">*</span></label>
                <input type="password" name="current_password" required placeholder="请输入当前密码">
            </div>
            <div>
                <label>新密码 <span style="color:red">*</span>（至少 8 位）</label>
                <input type="password" name="new_password" required placeholder="请输入新密码">
            </div>
            <div>
                <label>确认新密码 <span style="color:red">*</span></label>
                <input type="password" name="confirm_password" required placeholder="再输入一次新密码">
            </div>
        </div>
        <button class="btn" type="submit">确认修改</button>
    </form>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
