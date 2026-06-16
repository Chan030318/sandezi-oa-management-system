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
?>
