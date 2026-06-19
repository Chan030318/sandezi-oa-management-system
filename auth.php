<?php
session_start();
require_once __DIR__ . '/db.php';

function is_logged_in() {
    return isset($_SESSION['user']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function has_role($roles) {
    $user = current_user();
    if (!$user) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($user['role'], $roles);
}

function require_role($roles) {
    if (!has_role($roles)) {
        http_response_code(403);
        die("没有权限访问此页面");
    }
}

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die('无效的请求，请刷新页面后重试。');
    }
}

function write_audit_log($module, $action, $description) {
    global $pdo;
    $user    = current_user();
    $user_id = $user ? intval($user['id']) : null;
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        $pdo->prepare("
            INSERT INTO audit_logs (user_id, module, action, description, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$user_id, $module, $action, $description, $ip]);
    } catch (Exception $e) {
        error_log('audit_log error: ' . $e->getMessage());
    }
}
?>
