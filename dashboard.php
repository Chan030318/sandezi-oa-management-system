<?php
require_once __DIR__ . '/header.php';

$totalEmployees = $pdo->query("SELECT COUNT(*) AS total FROM employees")->fetch()['total'];
$totalDepartments = $pdo->query("SELECT COUNT(*) AS total FROM departments")->fetch()['total'];
$totalShifts = $pdo->query("SELECT COUNT(*) AS total FROM shifts")->fetch()['total'];
$totalSchedules = $pdo->query("SELECT COUNT(*) AS total FROM schedules")->fetch()['total'];
$totalAnnouncements = $pdo->query("SELECT COUNT(*) AS total FROM announcements")->fetch()['total'];

$todaySchedules = $pdo->query("
    SELECT COUNT(*) AS total 
    FROM schedules 
    WHERE work_date = CURDATE()
")->fetch()['total'];

$monthSchedules = $pdo->query("
    SELECT COUNT(*) AS total 
    FROM schedules
    WHERE MONTH(work_date) = MONTH(CURDATE()) 
    AND YEAR(work_date) = YEAR(CURDATE())
")->fetch()['total'];

$recentSchedules = $pdo->query("
    SELECT 
        s.work_date, 
        e.name AS employee_name, 
        d.name AS department_name, 
        sh.name AS shift_name, 
        sh.start_time, 
        sh.end_time
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    JOIN shifts sh ON s.shift_id = sh.id
    ORDER BY s.created_at DESC
    LIMIT 8
")->fetchAll();

$departmentStats = $pdo->query("
    SELECT 
        d.name AS department_name, 
        COUNT(e.id) AS total
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id
    GROUP BY d.id, d.name
    ORDER BY total DESC
")->fetchAll();
$pendingLeaves = $pdo->query("
    SELECT COUNT(*) AS total 
    FROM leaves 
    WHERE status = 'Pending'
")->fetch()['total'];

$approvedLeaves = $pdo->query("
    SELECT COUNT(*) AS total 
    FROM leaves 
    WHERE status = 'Approved'
")->fetch()['total'];

$monthLeaves = $pdo->query("
    SELECT COUNT(*) AS total 
    FROM leaves
    WHERE MONTH(start_date) = MONTH(CURDATE())
    AND YEAR(start_date) = YEAR(CURDATE())
")->fetch()['total'];

$announcements = $pdo->query("
    SELECT title, created_at 
    FROM announcements
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

function timeText($time){
    if (!$time) return '-';
    return substr($time, 0, 5);
}

$todayBookings = 0;
try {
    $todayBookings = $pdo->query("
        SELECT COUNT(*) FROM venue_bookings WHERE booking_date = CURDATE() AND status != '已取消'
    ")->fetchColumn();
} catch (Exception $e) {}

$recentAuditLogs = [];
if (has_role(['Admin'])) {
    $recentAuditLogs = $pdo->query("
        SELECT a.module, a.action, a.description, a.created_at, u.name AS user_name
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.id DESC
        LIMIT 10
    ")->fetchAll();
}
?>

<div class="page-title">
    <h2>Dashboard 数据看板</h2>
    <p>系统已连接数据库，以下为实时统计数据。</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span>员工总数</span>
        <h3><?= safe($totalEmployees) ?></h3>
        <p>已录入员工资料</p>
    </div>

    <div class="stat-card">
        <span>部门数量</span>
        <h3><?= safe($totalDepartments) ?></h3>
        <p>公司组织架构</p>
    </div>

    <div class="stat-card">
        <span>班次数量</span>
        <h3><?= safe($totalShifts) ?></h3>
        <p>已建立班次模板</p>
    </div>

    <div class="stat-card">
        <span>本月排班</span>
        <h3><?= safe($monthSchedules) ?></h3>
        <p>本月排班记录</p>
    </div>

    <div class="stat-card">
        <span>今日排班</span>
        <h3><?= safe($todaySchedules) ?></h3>
        <p>今日已安排班次</p>
    </div>

    <div class="stat-card">
        <span>公告数量</span>
        <h3><?= safe($totalAnnouncements) ?></h3>
        <p>系统公告记录</p>
    </div>
    
    <div class="stat-card">
    <span>待审批请假</span>
    <h3><?= safe($pendingLeaves) ?></h3>
    <p>等待主管审批</p>
</div>

<div class="stat-card">
    <span>已批准请假</span>
    <h3><?= safe($approvedLeaves) ?></h3>
    <p>已完成审批</p>
</div>

<div class="stat-card">
    <span>本月请假</span>
    <h3><?= safe($monthLeaves) ?></h3>
    <p>本月请假记录</p>
</div>

<div class="stat-card">
    <span>今日场地预约</span>
    <h3><?= safe($todayBookings) ?></h3>
    <p>今日有效预约数</p>
</div>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-head">
            <h2>最近排班记录</h2>
            <?php if (has_role(['Admin', 'Manager'])): ?>
                <a href="schedule.php">查看全部</a>
            <?php endif; ?>
        </div>

        <table>
            <tr>
                <th>日期</th>
                <th>员工</th>
                <th>部门</th>
                <th>班次</th>
                <th>时间</th>
            </tr>

            <?php if (empty($recentSchedules)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:#999;">暂无排班记录</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentSchedules as $row): ?>
                    <tr>
                        <td><?= safe($row['work_date']) ?></td>
                        <td><?= safe($row['employee_name']) ?></td>
                        <td><?= safe($row['department_name'] ?? '-') ?></td>
                        <td><span class="badge"><?= safe($row['shift_name']) ?></span></td>
                        <td><?= safe(timeText($row['start_time'])) ?> - <?= safe(timeText($row['end_time'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </section>

    <section class="panel">
        <h2>部门人数统计</h2>

        <?php if (empty($departmentStats)): ?>
            <p style="color:#999;">暂无部门资料</p>
        <?php else: ?>
            <?php foreach ($departmentStats as $dept): ?>
                <?php $width = min(100, max(5, (int)$dept['total'] * 12)); ?>

                <div class="dept-row">
                    <div class="dept-info">
                        <strong><?= safe($dept['department_name']) ?></strong>
                        <span><?= safe($dept['total']) ?> 人</span>
                    </div>
                    <div class="bar">
                        <div style="width: <?= $width ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <h2>最新公告</h2>

    <?php if (empty($announcements)): ?>
        <p style="color:#999;">暂无公告</p>
    <?php else: ?>
        <ul class="notice-list">
            <?php foreach ($announcements as $a): ?>
                <li>
                    <strong><?= safe($a['title']) ?></strong>
                    <span><?= safe(date('Y-m-d', strtotime($a['created_at']))) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if (has_role(['Admin']) && !empty($recentAuditLogs)): ?>
<section class="panel">
    <div class="panel-head">
        <h2>系统操作记录</h2>
        <a href="audit_logs.php">查看全部</a>
    </div>
    <table>
        <tr>
            <th>时间</th>
            <th>操作人</th>
            <th>模块</th>
            <th>动作</th>
            <th>说明</th>
        </tr>
        <?php foreach ($recentAuditLogs as $al): ?>
        <tr>
            <td style="white-space:nowrap;font-size:12px;color:#888;"><?= safe(substr($al['created_at'], 0, 16)) ?></td>
            <td><?= safe($al['user_name'] ?? '—') ?></td>
            <td><span class="badge"><?= safe($al['module']) ?></span></td>
            <td><?= safe($al['action']) ?></td>
            <td style="font-size:13px;color:#555;"><?= safe($al['description']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>