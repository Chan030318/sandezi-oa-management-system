<?php
require_once __DIR__ . '/header.php';

function getWeekDates($startDate = null) {
    $date = $startDate ? new DateTime($startDate) : new DateTime();
    $dayOfWeek = (int)$date->format('N');
    $date->modify('-' . ($dayOfWeek - 1) . ' days');

    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $d = clone $date;
        $d->modify("+$i days");
        $week[] = $d;
    }
    return $week;
}

$startDate = $_GET['week'] ?? date('Y-m-d');
$weekDates = getWeekDates($startDate);

$weekStart = $weekDates[0]->format('Y-m-d');
$weekEnd = $weekDates[6]->format('Y-m-d');

$prevWeek = (new DateTime($weekStart))->modify('-7 days')->format('Y-m-d');
$nextWeek = (new DateTime($weekStart))->modify('+7 days')->format('Y-m-d');

$employees = $pdo->query("
    SELECT 
        e.id,
        e.name,
        e.position,
        d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY d.id ASC, e.id ASC
")->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        s.employee_id,
        s.work_date,
        sh.name AS shift_name,
        sh.start_time,
        sh.end_time
    FROM schedules s
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.work_date BETWEEN ? AND ?
");
$stmt->execute([$weekStart, $weekEnd]);
$scheduleRows = $stmt->fetchAll();

$scheduleMap = [];

foreach ($scheduleRows as $row) {
    $scheduleMap[$row['employee_id']][$row['work_date']] = $row;
}

function shiftClass($name) {
    if (strpos($name, '早班') !== false) return 'shift-morning';
    if (strpos($name, '中班') !== false) return 'shift-mid';
    if (strpos($name, '晚班') !== false) return 'shift-night';
    if (strpos($name, '主播早班') !== false) return 'shift-live-morning';
    if (strpos($name, '主播中班') !== false) return 'shift-live-mid';
    if (strpos($name, '主播晚班') !== false) return 'shift-live-night';
    if (strpos($name, '休') !== false) return 'shift-off';
    return 'shift-default';
}

function shortTimeText($start, $end) {
    if (!$start || !$end) return '';
    return substr($start, 0, 5) . '-' . substr($end, 0, 5);
}

$weekNames = ['星期一','星期二','星期三','星期四','星期五','星期六','星期日'];
?>

<style>
.week-toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:18px;
}
.week-toolbar .actions{
    display:flex;
    gap:10px;
}
.schedule-table-wrap{
    overflow-x:auto;
}
.week-table{
    width:100%;
    border-collapse:collapse;
    min-width:1100px;
    background:#fff;
}
.week-table th,
.week-table td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center;
    vertical-align:middle;
}
.week-table th{
    background:#f6f6f6;
    font-weight:700;
}
.week-table td.info{
    background:#fafafa;
    font-weight:600;
}
.shift-badge{
    display:inline-block;
    min-width:88px;
    padding:6px 10px;
    border-radius:8px;
    font-size:13px;
    font-weight:700;
}
.shift-time{
    display:block;
    margin-top:4px;
    font-size:11px;
    opacity:.8;
}
.shift-morning{
    background:#dff3e6;
    color:#16703a;
}
.shift-mid{
    background:#e3f0ff;
    color:#185ea8;
}
.shift-night{
    background:#ffe7d1;
    color:#b65300;
}
.shift-live{
    background:#f0e4ff;
    color:#6a1b9a;
}
.shift-off{
    background:#dff5e8;
    color:#128047;
}
.shift-default{
    background:#eee;
    color:#333;
}
.empty-shift{
    color:#bbb;
}

.shift-morning{
    background:#dff3e6;
    color:#16703a;
}
.shift-mid{
    background:#e6f0ff;
    color:#185ea8;
}
.shift-night{
    background:#ffe1c7;
    color:#b65300;
}
.shift-live-morning{
    background:#eadcff;
    color:#6a1b9a;
}
.shift-live-mid{
    background:#fff0c2;
    color:#8a5a00;
}
.shift-live-night{
    background:#ffd6e8;
    color:#a0004f;
}
.shift-off{
    background:#dff5e8;
    color:#128047;
}
.shift-default{
    background:#eee;
    color:#333;
}
</style>

<div class="page-title">
    <h2>周排班表</h2>
    <p>按部门、岗位、员工查看一周排班情况。</p>
</div>

<section class="panel">
    <div class="week-toolbar">
        <div>
            <h2>排班表 <?= safe($weekStart) ?> 至 <?= safe($weekEnd) ?></h2>
        </div>

        <div class="actions">
            <a class="btn secondary" href="weekly_schedule.php?week=<?= safe($prevWeek) ?>">上一周</a>
            <a class="btn" href="weekly_schedule.php">本周</a>
            <a class="btn secondary" href="weekly_schedule.php?week=<?= safe($nextWeek) ?>">下一周</a>
            <a class="btn" href="schedule.php">新增排班</a>
        </div>
    </div>

    <div class="schedule-table-wrap">
        <table class="week-table">
            <tr>
                <th>部门</th>
                <th>岗位</th>
                <th>姓名</th>
                <?php foreach ($weekDates as $index => $date): ?>
                    <th>
                        <?= safe($date->format('m.d')) ?><br>
                        <?= safe($weekNames[$index]) ?>
                    </th>
                <?php endforeach; ?>
            </tr>

            <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="10" style="color:#999;">暂无员工资料</td>
                </tr>
            <?php else: ?>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td class="info"><?= safe($emp['department_name'] ?? '-') ?></td>
                        <td class="info"><?= safe($emp['position'] ?? '-') ?></td>
                        <td class="info"><?= safe($emp['name']) ?></td>

                        <?php foreach ($weekDates as $date): ?>
                            <?php
                                $day = $date->format('Y-m-d');
                                $shift = $scheduleMap[$emp['id']][$day] ?? null;
                            ?>

                            <td>
                                <?php if ($shift): ?>
                                    <span class="shift-badge <?= safe(shiftClass($shift['shift_name'])) ?>">
                                        <?= safe($shift['shift_name']) ?>
                                        <span class="shift-time">
                                            <?= safe(shortTimeText($shift['start_time'], $shift['end_time'])) ?>
                                        </span>
                                    </span>
                                <?php else: ?>
                                    <span class="empty-shift">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>