<?php
require_once __DIR__ . '/header.php';

$message = "";

// 新增排班
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $stmt = $pdo->prepare("
       INSERT INTO schedules (employee_id, shift_id, work_date, remark)
VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
shift_id = VALUES(shift_id),
remark = VALUES(remark),
created_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $_POST['employee_id'],
        $_POST['shift_id'],
        $_POST['work_date'],
        $_POST['remark']
    ]);

    $message = "排班新增成功";
}

// 编辑排班
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $stmt = $pdo->prepare("
        UPDATE schedules
        SET employee_id = ?, shift_id = ?, work_date = ?, remark = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['employee_id'],
        $_POST['shift_id'],
        $_POST['work_date'],
        $_POST['remark'],
        $_POST['id']
    ]);

    $message = "排班已更新";
}

// 删除排班
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = "排班已删除";
}

// 员工列表
$employees = $pdo->query("
    SELECT e.id, e.name, e.position, d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.id ASC
")->fetchAll();

// 班次列表
$shifts = $pdo->query("
    SELECT * FROM shifts
    ORDER BY id ASC
")->fetchAll();

// 编辑资料
$editSchedule = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editSchedule = $stmt->fetch();
}

// 排班记录
$schedules = $pdo->query("
    SELECT 
        s.id,
        s.work_date,
        s.remark,
        e.name AS employee_name,
        e.position,
        d.name AS department_name,
        sh.name AS shift_name,
        sh.start_time,
        sh.end_time
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    JOIN shifts sh ON s.shift_id = sh.id
    ORDER BY s.work_date DESC, s.created_at DESC
")->fetchAll();

function shortTime($time) {
    if (!$time) return "-";
    return substr($time, 0, 5);
}
?>

<div class="page-title">
    <h2>排班管理</h2>
    <p>管理员工每日班次安排，数据会同步到 Dashboard。</p>
</div>

<?php if ($message): ?>
    <div class="panel" style="color:#9b1c1c;font-weight:bold;">
        <?= safe($message) ?>
    </div>
<?php endif; ?>

<section class="panel">
    <h2><?= $editSchedule ? '编辑排班' : '新增排班' ?></h2>

    <form method="POST">
        <input type="hidden" name="action" value="<?= $editSchedule ? 'edit' : 'add' ?>">

        <?php if ($editSchedule): ?>
            <input type="hidden" name="id" value="<?= safe($editSchedule['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>员工</label>
                <select name="employee_id" required>
                    <option value="">请选择员工</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= safe($e['id']) ?>"
                            <?= (($editSchedule['employee_id'] ?? '') == $e['id']) ? 'selected' : '' ?>>
                            <?= safe($e['name']) ?> - <?= safe($e['department_name'] ?? '-') ?> - <?= safe($e['position']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>日期</label>
                <input type="date" name="work_date" required value="<?= safe($editSchedule['work_date'] ?? date('Y-m-d')) ?>">
            </div>

            <div>
                <label>班次</label>
                <select name="shift_id" required>
                    <option value="">请选择班次</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?= safe($s['id']) ?>"
                            <?= (($editSchedule['shift_id'] ?? '') == $s['id']) ? 'selected' : '' ?>>
                            <?= safe($s['name']) ?>（<?= safe(shortTime($s['start_time'])) ?> - <?= safe(shortTime($s['end_time'])) ?>）
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>备注</label>
                <input type="text" name="remark" value="<?= safe($editSchedule['remark'] ?? '') ?>" placeholder="例如：临时调整 / 代班">
            </div>
        </div>

        <button class="btn" type="submit">
            <?= $editSchedule ? '保存修改' : '新增排班' ?>
        </button>

        <?php if ($editSchedule): ?>
            <a class="btn secondary" href="schedule.php">取消编辑</a>
        <?php endif; ?>
    </form>
</section>

<section class="panel">
    <h2>排班记录</h2>

    <table>
        <tr>
            <th>日期</th>
            <th>员工</th>
            <th>部门</th>
            <th>岗位</th>
            <th>班次</th>
            <th>时间</th>
            <th>备注</th>
            <th>操作</th>
        </tr>

        <?php if (empty($schedules)): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#999;">暂无排班记录</td>
            </tr>
        <?php else: ?>
            <?php foreach ($schedules as $row): ?>
                <tr>
                    <td><?= safe($row['work_date']) ?></td>
                    <td><?= safe($row['employee_name']) ?></td>
                    <td><?= safe($row['department_name'] ?? '-') ?></td>
                    <td><?= safe($row['position']) ?></td>
                    <td><span class="badge"><?= safe($row['shift_name']) ?></span></td>
                    <td><?= safe(shortTime($row['start_time'])) ?> - <?= safe(shortTime($row['end_time'])) ?></td>
                    <td><?= safe($row['remark'] ?? '-') ?></td>
                    <td>
                        <a href="schedule.php?edit=<?= safe($row['id']) ?>">编辑</a>
                        |
                        <a href="schedule.php?delete=<?= safe($row['id']) ?>"
                           onclick="return confirm('确定要删除这条排班吗？')">
                           删除
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>