<?php
require_once __DIR__ . '/auth.php';
require_login();
$user = current_user();
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= safe(APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">

</head>
<body>
<div class="layout">
   <aside class="sidebar">
        <div class="brand">
            <img src="assets/logo.jpg" alt="三德子" class="brand-logo-img">
            <div>
                <h2>三德子</h2>
                <p>OA 管理系统</p>
            </div>
        </div>

        <div class="user-box">
            <strong><?= safe($user['name']) ?></strong>
            <span><?= safe($user['role']) ?></span>
        </div>

        <nav class="menu">
            <a class="<?= $current=='dashboard.php'?'active':'' ?>" href="dashboard.php">🏠 Dashboard</a>

            <?php if (has_role(['Admin', 'Manager'])): ?>
                <a class="<?= $current=='employees.php'?'active':'' ?>" href="employees.php">👥 员工管理</a>
                <a class="<?= $current=='departments.php'?'active':'' ?>" href="departments.php">🏢 部门管理</a>
                <a class="<?= $current=='schedule.php'?'active':'' ?>" href="schedule.php">📅 排班管理</a>
                <a class="<?= $current=='weekly_schedule.php'?'active':'' ?>" href="weekly_schedule.php">📅 周排班表</a>
                <a class="<?= $current=='shifts.php'?'active':'' ?>" href="shifts.php">⏰ 班次管理</a>
                <a class="<?= $current=='leave_manage.php'?'active':'' ?>" href="leave_manage.php">✅ 请假管理</a>
                <a class="<?= $current=='reports.php'?'active':'' ?>" href="reports.php">📊 报表导出</a>
            <?php endif; ?>

            <?php if (has_role(['Admin'])): ?>
                <a class="<?= in_array($current,['users.php','user_edit.php'])?'active':'' ?>" href="users.php">👤 用户管理</a>
                <a class="<?= $current=='login_logs.php'?'active':'' ?>" href="login_logs.php">📋 登录日志</a>
            <?php endif; ?>

            <a class="<?= $current=='announcements.php'?'active':'' ?>" href="announcements.php">📢 公告中心</a>
            <a class="<?= $current=='leave_apply.php'?'active':'' ?>" href="leave_apply.php">📝 申请请假</a>
            <a class="<?= $current=='my_schedule.php'?'active':'' ?>" href="my_schedule.php">🗓️ 我的排班</a>
            <a class="<?= $current=='my_leave.php'?'active':'' ?>" href="my_leave.php">📄 我的请假</a>
            <a class="<?= $current=='profile.php'?'active':'' ?>" href="profile.php">👤 个人资料</a>
            <a class="<?= $current=='change_password.php'?'active':'' ?>" href="change_password.php">🔑 修改密码</a>
            <a href="logout.php">🚪 退出登录</a>
        </nav>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1><?= safe(APP_NAME) ?></h1>
                <p><?= safe(APP_SUBTITLE) ?></p>
            </div>
            <div class="top-actions">
                <span><?= date('Y-m-d') ?></span>
            </div>
        </div>
