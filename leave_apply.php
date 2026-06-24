<?php
ob_start();
require_once __DIR__ . '/header.php';

$message    = '';
$isManager  = has_role(['Admin', 'Manager']);
$sessionEid = $_SESSION['user']['employee_id'] ?? null;
$sessionName = $_SESSION['user']['name'] ?? '';

$employees = [];
if ($isManager) {
    $employees = $pdo->query("SELECT id, name FROM employees ORDER BY name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($isManager) {
        $employeeId = intval($_POST['employee_id'] ?? 0);
    } else {
        $employeeId = intval($sessionEid);
    }

    if (!$employeeId) {
        $message = 'error:此账号尚未绑定员工资料，无法提交请假申请。';
    } else {
        $allowed_types = ['年假','病假','事假','婚假','产假','陪产假','丧假','其他','调休','出差','外出'];
        $leave_type    = $_POST['leave_type'] ?? '';
        $start_date    = $_POST['start_date'] ?? '';
        $end_date      = $_POST['end_date']   ?? '';
        $reason        = trim($_POST['reason'] ?? '');

        if (!in_array($leave_type, $allowed_types, true)) {
            $message = 'error:申请类型无效，请重新选择。';
        } elseif (!$start_date || !$end_date) {
            $message = 'error:请填写请假开始与结束日期。';
        } elseif ($end_date < $start_date) {
            $message = 'error:结束日期不能早于开始日期。';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$employeeId, $leave_type, $start_date, $end_date, $reason]);
            write_audit_log('请假申请', '提交请假', "员工 ID {$employeeId} 申请 {$leave_type} {$start_date}~{$end_date}");
            $message = 'success:请假申请提交成功，请等待主管审批。';
        }
    }
}

[$msgType, $msgText] = $message ? explode(':', $message, 2) : ['', ''];
?>

<div class="page-title">
    <h2>申请请假 / 出差 / 外出</h2>
    <p>提交申请后等待主管审批，审批通过后自动写入排班表。</p>
</div>

<?php if ($msgText): ?>
    <div class="<?= $msgType === 'error' ? 'alert' : 'success' ?>"><?= safe($msgText) ?></div>
<?php endif; ?>

<?php if (!$isManager && !$sessionEid): ?>
    <div class="alert">此账号尚未绑定员工资料，无法提交请假申请。请联系管理员处理。</div>
<?php else: ?>

<section class="panel">
    <form method="POST">
        <?= csrf_field() ?>

        <?php if ($isManager): ?>
            <label>员工 <span style="color:red">*</span>
                <select name="employee_id" required>
                    <option value="">请选择员工</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= safe($e['id']) ?>"><?= safe($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <label>申请人</label>
            <p style="font-weight:bold;margin:4px 0 12px;"><?= safe($sessionName) ?></p>
        <?php endif; ?>

        <label>申请类型
            <select name="leave_type">
                <optgroup label="─── 请假 ───">
                    <option>年假</option>
                    <option>病假</option>
                    <option>事假</option>
                    <option>婚假</option>
                    <option>产假</option>
                    <option>陪产假</option>
                    <option>丧假</option>
                    <option>其他</option>
                </optgroup>
                <optgroup label="─── 出勤异常 ───">
                    <option>调休</option>
                    <option>出差</option>
                    <option>外出</option>
                </optgroup>
            </select>
        </label>

        <div class="form-grid" style="margin-top:12px;">
            <label>开始日期 <span style="color:red">*</span>
                <input type="date" name="start_date" required>
            </label>
            <label>结束日期 <span style="color:red">*</span>
                <input type="date" name="end_date" required>
            </label>
        </div>

        <label style="margin-top:12px;">原因
            <textarea name="reason" placeholder="请简述请假原因（可选）"></textarea>
        </label>

        <button type="submit" style="margin-top:12px;">提交申请</button>
    </form>
</section>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
