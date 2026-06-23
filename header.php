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

        <nav class="menu" id="sidebar-nav">

            <!-- 一、总览 -->
            <div class="nav-group" id="grp-overview">
                <button class="nav-group-btn" onclick="sdToggle('grp-overview')">
                    <span class="grp-label">总览</span><span class="nav-arr">›</span>
                </button>
                <div class="nav-group-body">
                    <a class="<?= $current=='dashboard.php'?'active':'' ?>" href="dashboard.php">🏠 Dashboard</a>
                </div>
            </div>

            <!-- 二、员工与组织 -->
            <div class="nav-group" id="grp-people">
                <button class="nav-group-btn" onclick="sdToggle('grp-people')">
                    <span class="grp-label">员工与组织</span><span class="nav-arr">›</span>
                </button>
                <div class="nav-group-body">
                    <?php if (has_role(['Admin', 'Manager'])): ?>
                        <a class="<?= $current=='employees.php'?'active':'' ?>" href="employees.php">👥 员工管理</a>
                        <a class="<?= $current=='departments.php'?'active':'' ?>" href="departments.php">🏢 部门管理</a>
                    <?php endif; ?>
                    <?php if (has_role(['Admin'])): ?>
                        <a class="<?= in_array($current,['users.php','user_edit.php'])?'active':'' ?>" href="users.php">👤 用户管理</a>
                    <?php endif; ?>
                    <a class="<?= $current=='profile.php'?'active':'' ?>" href="profile.php">👤 个人资料</a>
                    <a class="<?= $current=='change_password.php'?'active':'' ?>" href="change_password.php">🔑 修改密码</a>
                </div>
            </div>

            <!-- 三、排班与请假 -->
            <div class="nav-group" id="grp-schedule">
                <button class="nav-group-btn" onclick="sdToggle('grp-schedule')">
                    <span class="grp-label">排班与请假</span><span class="nav-arr">›</span>
                </button>
                <div class="nav-group-body">
                    <?php if (has_role(['Admin', 'Manager'])): ?>
                        <a class="<?= $current=='schedule.php'?'active':'' ?>" href="schedule.php">📅 排班管理</a>
                        <a class="<?= $current=='schedule_batch.php'?'active':'' ?>" href="schedule_batch.php">📋 批量排班</a>
                        <a class="<?= $current=='weekly_schedule.php'?'active':'' ?>" href="weekly_schedule.php">📅 周排班表</a>
                        <a class="<?= $current=='shifts.php'?'active':'' ?>" href="shifts.php">⏰ 班次管理</a>
                        <a class="<?= $current=='leave_manage.php'?'active':'' ?>" href="leave_manage.php">✅ 请假审批</a>
                    <?php endif; ?>
                    <a class="<?= $current=='my_schedule.php'?'active':'' ?>" href="my_schedule.php">🗓️ 我的排班</a>
                    <a class="<?= $current=='leave_apply.php'?'active':'' ?>" href="leave_apply.php">📝 申请请假</a>
                    <a class="<?= $current=='my_leave.php'?'active':'' ?>" href="my_leave.php">📄 我的请假</a>
                </div>
            </div>

            <!-- 四、设备管理 -->
            <div class="nav-group" id="grp-device">
                <button class="nav-group-btn" onclick="sdToggle('grp-device')">
                    <span class="grp-label">设备管理</span><span class="nav-arr">›</span>
                </button>
                <div class="nav-group-body">
                    <?php if (has_role(['Admin', 'Manager'])): ?>
                        <a class="<?= $current=='devices.php'?'active':'' ?>" href="devices.php">📦 设备管理</a>
                    <?php endif; ?>
                    <a class="<?= $current=='device_borrow.php'?'active':'' ?>" href="device_borrow.php">📤 设备借用</a>
                    <?php if (has_role(['Admin', 'Manager'])): ?>
                        <a class="<?= $current=='device_borrow_manage.php'?'active':'' ?>" href="device_borrow_manage.php">📋 借用审批</a>
                    <?php endif; ?>
                    <a class="<?= $current=='device_maintenance.php'?'active':'' ?>" href="device_maintenance.php">🛠 设备维修</a>
                </div>
            </div>

            <!-- 五、场地管理 -->
            <div class="nav-group" id="grp-venue">
                <button class="nav-group-btn" onclick="sdToggle('grp-venue')">
                    <span class="grp-label">场地管理</span><span class="nav-arr">›</span>
                </button>
                <div class="nav-group-body">
                    <?php if (has_role(['Admin', 'Manager'])): ?>
                        <a class="<?= $current=='venues.php'?'active':'' ?>" href="venues.php">🏢 场地管理</a>
                    <?php endif; ?>
                    <a class="<?= $current=='venue_bookings.php'?'active':'' ?>" href="venue_bookings.php">📅 场地预约</a>
                </div>
            </div>

            <!-- 六、公告与报表 -->
            <div class="nav-group" id="grp-announce">
                <button class="nav-group-btn" onclick="sdToggle('grp-announce')">
                    <span class="grp-label">公告与报表</span><span class="nav-arr">›</span>
                </button>
                <div class="nav-group-body">
                    <a class="<?= $current=='announcements.php'?'active':'' ?>" href="announcements.php">📢 公告中心</a>
                    <?php if (has_role(['Admin', 'Manager'])): ?>
                        <a class="<?= $current=='reports.php'?'active':'' ?>" href="reports.php">📊 报表导出</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 七、系统日志 (Admin only) -->
            <?php if (has_role(['Admin'])): ?>
            <div class="nav-group" id="grp-logs">
                <button class="nav-group-btn" onclick="sdToggle('grp-logs')">
                    <span class="grp-label">系统日志</span><span class="nav-arr">›</span>
                </button>
                <div class="nav-group-body">
                    <a class="<?= $current=='login_logs.php'?'active':'' ?>" href="login_logs.php">📋 登录日志</a>
                    <a class="<?= $current=='audit_logs.php'?'active':'' ?>" href="audit_logs.php">🔍 操作日志</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- 退出 -->
            <div class="nav-logout">
                <a href="logout.php">🚪 退出登录</a>
            </div>

        </nav>

        <script>
        function sdToggle(id) {
            document.getElementById(id).classList.toggle('open');
        }
        // Auto-open the group that contains the active link
        document.querySelectorAll('.nav-group').forEach(function(g) {
            if (g.querySelector('a.active')) g.classList.add('open');
        });
        // Fallback: open overview if nothing is active
        if (!document.querySelector('.nav-group.open')) {
            var fb = document.getElementById('grp-overview');
            if (fb) fb.classList.add('open');
        }
        </script>
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
