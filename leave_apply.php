<?php
require_once __DIR__ . '/header.php';

$message    = '';
$isManager  = has_role(['Admin', 'Manager']);
$sessionEid = $_SESSION['user']['employee_id'] ?? null;
$sessionName = $_SESSION['user']['name'] ?? '';

// 员工下拉列表（仅 Admin / Manager 使用）
$employees = [];
if ($isManager) {
    $employees = $pdo->query("
        SELECT id, name FROM employees ORDER BY name
    ")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Employee 强制使用自己的 employee_id，忽略前端传值
    if ($isManager) {
        $employeeId = intval($_POST['employee_id'] ?? 0);
    } else {
        $employeeId = intval($sessionEid);
    }

    if (!$employeeId) {
        $message = 'error:此账号尚未绑定员工资料，无法提交请假申请。';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $employeeId,
            $_POST['leave_type'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['reason'] ?? ''
        ]);
        $message = 'success:请假申请提交成功';
    }
}

$msgType = '';
$msgText = '';
if ($message) {
    [$msgType, $msgText] = explode(':', $message, 2);
}
?>

<div class="page-title">
    <h2>申请请假</h2>
    <p>员工提交请假申请</p>
</div>

<?php if ($msgText): ?>
<div class="panel" style="font-weight:bold;color:<?= $msgType === 'error' ? '#9b1c1c' : '#166534' ?>;">
    <?= safe($msgText) ?>
</div>
<?php endif; ?>

<?php if (!$isManager && !$sessionEid): ?>
<div class="panel" style="color:#9b1c1c;">
    此账号尚未绑定员工资料，无法提交请假申请。请联系管理员处理。
</div>
<?php else: ?>

<section class="panel">
<form method="POST">

    <?php if ($isManager): ?>
        <p>员工</p>
        <select name="employee_id" required>
            <option value="">请选择员工</option>
            <?php foreach ($employees as $e): ?>
                <option value="<?= safe($e['id']) ?>"><?= safe($e['name']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <p>申请人</p>
        <p><strong><?= safe($sessionName) ?></strong></p>
    <?php endif; ?>

    <p>请假类型</p>
    <select name="leave_type">
        <option>年假</option>
        <option>病假</option>
        <option>事假</option>
        <option>调休</option>
    </select>

    <p>开始日期</p>
    <input type="date" name="start_date" required>

    <p>结束日期</p>
    <input type="date" name="end_date" required>

    <p>原因</p>
    <textarea name="reason"></textarea>

    <br><br>

    <button type="submit">提交申请</button>

</form>
</section>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>