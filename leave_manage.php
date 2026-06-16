<?php
require_once __DIR__ . '/header.php';

$message = '';

if (isset($_GET['approve'])) {
    $stmt = $pdo->prepare("
        UPDATE leaves
        SET status = 'Approved', approve_remark = '已批准'
        WHERE id = ?
    ");
    $stmt->execute([$_GET['approve']]);
    $message = '请假申请已批准';
}

if (isset($_GET['reject'])) {
    $stmt = $pdo->prepare("
        UPDATE leaves
        SET status = 'Rejected', approve_remark = '已拒绝'
        WHERE id = ?
    ");
    $stmt->execute([$_GET['reject']]);
    $message = '请假申请已拒绝';
}

$leaves = $pdo->query("
    SELECT 
        l.*,
        e.name AS employee_name,
        e.position,
        d.name AS department_name
    FROM leaves l
    JOIN employees e ON l.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY l.created_at DESC
")->fetchAll();

function leaveStatusText($status) {
    if ($status === 'Pending') return '待审批';
    if ($status === 'Approved') return '已批准';
    if ($status === 'Rejected') return '已拒绝';
    return $status;
}
?>

<div class="page-title">
    <h2>请假管理</h2>
    <p>管理员审批员工请假申请。</p>
</div>

<?php if($message): ?>
<div class="panel" style="color:#9b1c1c;font-weight:bold;">
    <?= safe($message) ?>
</div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h2>请假申请列表</h2>
        <a href="leave_apply.php">新增请假</a>
    </div>

    <table>
        <tr>
            <th>员工</th>
            <th>部门</th>
            <th>岗位</th>
            <th>类型</th>
            <th>开始日期</th>
            <th>结束日期</th>
            <th>原因</th>
            <th>状态</th>
            <th>操作</th>
        </tr>

        <?php if(empty($leaves)): ?>
            <tr>
                <td colspan="9" style="text-align:center;color:#999;">暂无请假申请</td>
            </tr>
        <?php else: ?>
            <?php foreach($leaves as $l): ?>
                <tr>
                    <td><?= safe($l['employee_name']) ?></td>
                    <td><?= safe($l['department_name'] ?? '-') ?></td>
                    <td><?= safe($l['position'] ?? '-') ?></td>
                    <td><?= safe($l['leave_type']) ?></td>
                    <td><?= safe($l['start_date']) ?></td>
                    <td><?= safe($l['end_date']) ?></td>
                    <td><?= safe($l['reason']) ?></td>
                    <td><span class="badge"><?= safe(leaveStatusText($l['status'])) ?></span></td>
                    <td>
                        <?php if($l['status'] === 'Pending'): ?>
                            <a href="leave_manage.php?approve=<?= safe($l['id']) ?>">批准</a>
                            |
                            <a href="leave_manage.php?reject=<?= safe($l['id']) ?>">拒绝</a>
                        <?php else: ?>
                            <?= safe($l['approve_remark'] ?? '-') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>