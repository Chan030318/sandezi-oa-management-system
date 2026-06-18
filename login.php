<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT u.*, e.department_id, e.position
        FROM users u
        LEFT JOIN employees e ON u.employee_id = e.id
        WHERE u.email = ? AND u.status = 'active'
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'employee_id' => $user['employee_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id'] ?? null,
            'position' => $user['position'] ?? ''
        ];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "邮箱或密码错误";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>登录 - 三德子 OA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
    <div class="login-wrap">
        <div class="login-intro">
            <img src="assets/logo.jpg" alt="三德子" class="login-logo">
            <h1>三德子 OA 管理系统</h1>
            <p>员工管理 · 排班管理 · 数据看板</p>
        </div>

        <div class="login-card">
            <h2>账号登录</h2>
            <p class="muted">第一阶段测试环境</p>

            <?php if ($error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>邮箱</label>
                <input type="email" name="email" required placeholder="admin@sandezi.com">

                <label>密码</label>
                <input type="password" name="password" required placeholder="password">

                <button type="submit">登录系统</button>
            </form>

            <div class="demo-account">
                测试账号：admin@sandezi.com / password
            </div>
        </div>
    </div>
</body>
</html>
