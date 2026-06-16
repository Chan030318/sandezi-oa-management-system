<?php
require_once __DIR__ . '/header.php';

$userName    = $_SESSION['user']['name'] ?? '';
$employeeId  = $_SESSION['user']['employee_id'] ?? null;
$leaves      = [];
$noEmployee  = false;

if ($employeeId) {
    $stmt = $pdo->prepare("
        SELECT l.*
        FROM leaves l
        WHERE l.employee_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $leaves = $stmt->fetchAll();
} else {
    $noEmployee = true;
}

function leaveStatusText($status) {
    if ($status === 'Pending') return '待审批';
    if ($status === 'Approved') return '已批准';
    if ($status === 'Rejected') return '已拒绝';
    return $status;
}
?>

<div class="page-title">
    <h2>我的请假</h2>
    <p>查看个人请假申请记录。</p>
</div>

<section class="panel">
    <div class="panel-head">
        <h2><?= safe($userName) ?> 的请假记录</h2>
        <a href="leave_apply.php">申请请假</a>
    </div>

    <?php if ($noEmployee): ?>
        <p style="color:#999;">此账号尚未绑定员工资料，暂无记录。</p>
    <?php else: ?>
    <table>
        <tr>
            <th>类型</th>
            <th>开始日期</th>
            <th>结束日期</th>
            <th>原因</th>
            <th>状态</th>
            <th>审批备注</th>
        </tr>

        <?php if (empty($leaves)): ?>
            <tr>
                <td colspan="6" style="text-align:center;color:#999;">暂无请假记录</td>
            </tr>
        <?php else: ?>
            <?php foreach ($leaves as $l): ?>
                <tr>
                    <td><?= safe($l['leave_type']) ?></td>
                    <td><?= safe($l['start_date']) ?></td>
                    <td><?= safe($l['end_date']) ?></td>
                    <td><?= safe($l['reason']) ?></td>
                    <td><span class="badge"><?= safe(leaveStatusText($l['status'])) ?></span></td>
                    <td><?= safe($l['approve_remark'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>