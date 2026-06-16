<?php
require_once __DIR__ . '/header.php';

$message = '';

$employees = $pdo->query("
    SELECT id,name
    FROM employees
    ORDER BY name
")->fetchAll();

if($_SERVER['REQUEST_METHOD']=='POST'){

    $stmt = $pdo->prepare("
        INSERT INTO leaves
        (employee_id,leave_type,start_date,end_date,reason)
        VALUES (?,?,?,?,?)
    ");

    $stmt->execute([
        $_POST['employee_id'],
        $_POST['leave_type'],
        $_POST['start_date'],
        $_POST['end_date'],
        $_POST['reason']
    ]);

    $message = "请假申请提交成功";
}
?>

<div class="page-title">
    <h2>申请请假</h2>
    <p>员工提交请假申请</p>
</div>

<?php if($message): ?>
<div class="panel">
    <?= $message ?>
</div>
<?php endif; ?>

<section class="panel">

<form method="POST">

<p>员工</p>
<select name="employee_id" required>
<?php foreach($employees as $e): ?>
<option value="<?= $e['id'] ?>">
<?= $e['name'] ?>
</option>
<?php endforeach; ?>
</select>

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

<button type="submit">
提交申请
</button>

</form>

</section>

<?php require_once __DIR__.'/footer.php'; ?>