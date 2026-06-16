<?php
require_once __DIR__ . '/header.php';

$userName = $_SESSION['user']['name'] ?? $_SESSION['username'] ?? '系统管理员';

$stmt = $pdo->prepare("
    SELECT 
        l.*,
        e.name AS employee_name
    FROM leaves l
    JOIN employees e ON l.employee_id = e.id
    WHERE e.name = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$userName]);
$leaves = $stmt->fetchAll();

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
</section>

<?php require_once __DIR__ . '/footer.php'; ?>