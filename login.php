<?php
// Session cookie hardening (mirrors auth.php — login.php runs before auth.php)
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $is_https,
]);
session_start();
require_once __DIR__ . '/db.php';

$error = '';

// ── 速率限制常量 ────────────────────────────────────────────────
define('LOGIN_MAX_FAILS',    5);
define('LOGIN_LOCKOUT_SECS', 600); // 10 分钟

// ── CSRF 辅助（login.php 不加载 auth.php，手动实现）────────────
function login_csrf_token(): string {
    if (empty($_SESSION['login_csrf'])) {
        $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['login_csrf'];
}
function login_csrf_field(): string {
    return '<input type="hidden" name="login_csrf" value="' . login_csrf_token() . '">';
}
function login_verify_csrf(): bool {
    $token = $_POST['login_csrf'] ?? '';
    return $token !== '' && hash_equals($_SESSION['login_csrf'] ?? '', $token);
}

// ── 写登录日志辅助函数 ──────────────────────────────────────────
function write_login_log($pdo, $email, $user_id, $status) {
    $ip         = $_SERVER['REMOTE_ADDR']     ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $pdo->prepare("
        INSERT INTO login_logs (email, user_id, ip, user_agent, status)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$email, $user_id, $ip, $user_agent, $status]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF 验证 ───────────────────────────────────────────────
    if (!login_verify_csrf()) {
        $error = '无效的请求，请刷新页面后重试。';
    } else {
        // ── 锁定检测 ─────────────────────────────────────────────
        $fail_count  = $_SESSION['login_fails']      ?? 0;
        $locked_at   = $_SESSION['login_locked_at']  ?? 0;
        $now         = time();

        if ($fail_count >= LOGIN_MAX_FAILS) {
            $remaining = LOGIN_LOCKOUT_SECS - ($now - $locked_at);
            if ($remaining > 0) {
                $mins = ceil($remaining / 60);
                $error = "登录失败次数过多，请 {$mins} 分钟后再试。";
            } else {
                // 锁定已到期，重置
                $_SESSION['login_fails']     = 0;
                $_SESSION['login_locked_at'] = 0;
                $fail_count = 0;
            }
        }

        if ($error === '') {
            $email    = trim($_POST['email']    ?? '');
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
                // 登录成功：清除失败记数，重新生成 CSRF token
                unset($_SESSION['login_fails'], $_SESSION['login_locked_at'], $_SESSION['login_csrf']);
                $_SESSION['user'] = [
                    'id'            => $user['id'],
                    'employee_id'   => $user['employee_id'],
                    'name'          => $user['name'],
                    'email'         => $user['email'],
                    'role'          => $user['role'],
                    'department_id' => $user['department_id'] ?? null,
                    'position'      => $user['position'] ?? ''
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                write_login_log($pdo, $email, $user['id'], 'success');
                header("Location: dashboard.php");
                exit;
            } else {
                // 登录失败：递增失败次数
                $_SESSION['login_fails'] = ($fail_count + 1);
                if ($_SESSION['login_fails'] >= LOGIN_MAX_FAILS) {
                    $_SESSION['login_locked_at'] = $now;
                }
                $remaining_attempts = max(0, LOGIN_MAX_FAILS - $_SESSION['login_fails']);
                $error = $remaining_attempts > 0
                    ? "邮箱或密码错误（还可尝试 {$remaining_attempts} 次）"
                    : '登录失败次数过多，账号已锁定 10 分钟。';
                $user_id_log = $user['id'] ?? null;
                write_login_log($pdo, $email, $user_id_log, 'failed');
            }
        }
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
            <p class="muted">企业内部管理系统</p>

            <?php if ($error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= login_csrf_field() ?>
                <label>邮箱</label>
                <input type="email" name="email" required placeholder="your@sandezi.com">

                <label>密码</label>
                <input type="password" name="password" required>

                <button type="submit">登录系统</button>
            </form>
        </div>
    </div>
</body>
</html>
