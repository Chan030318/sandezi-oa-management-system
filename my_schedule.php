<?php
require_once __DIR__ . '/header.php';

$userName = $_SESSION['user']['name'] ?? $_SESSION['username'] ?? '系统管理员';

$stmt = $pdo->prepare("
    SELECT 
        s.work_date,
        s.remark,
        sh.name AS shift_name,
        sh.start_time,
        sh.end_time
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE e.name = ?
    ORDER BY s.work_date ASC
");

$stmt->execute([$userName]);
$mySchedules = $stmt->fetchAll();

function shortTime($time) {
    if (!$time) return '-';
    return substr($time, 0, 5);
}
?>

<div class="page-title">
    <h2>我的排班</h2>
    <p>查看个人排班记录。</p>
</div>

<section class="panel">
    <h2><?= safe($userName) ?> 的排班</h2>

    <table>
        <tr>
            <th>日期</th>
            <th>班次</th>
            <th>时间</th>
            <th>备注</th>
        </tr>

        <?php if (empty($mySchedules)): ?>
            <tr>
                <td colspan="4" style="text-align:center;color:#999;">暂无个人排班记录</td>
            </tr>
        <?php else: ?>
            <?php foreach ($mySchedules as $row): ?>
                <tr>
                    <td><?= safe($row['work_date']) ?></td>
                    <td><span class="badge"><?= safe($row['shift_name']) ?></span></td>
                    <td><?= safe(shortTime($row['start_time'])) ?> - <?= safe(shortTime($row['end_time'])) ?></td>
                    <td><?= safe($row['remark'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>