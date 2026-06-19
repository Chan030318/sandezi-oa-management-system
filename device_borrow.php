<?php
require_once __DIR__ . '/header.php';
// 所有已登录用户均可申请借用

$message = '';
$error   = '';
$me      = current_user();

// ── 申请借用 ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    verify_csrf();

    $device_id    = intval($_POST['device_id']    ?? 0);
    $purpose      = trim($_POST['purpose']        ?? '');
    $borrow_start = trim($_POST['borrow_start']   ?? '');
    $borrow_end   = trim($_POST['borrow_end']     ?? '');

    // 基础验证
    if ($device_id <= 0) {
        $error = '请选择设备。';
    } elseif ($purpose === '') {
        $error = '请填写借用用途。';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $borrow_start) ||
              !preg_match('/^\d{4}-\d{2}-\d{2}$/', $borrow_end)) {
        $error = '日期格式不正确。';
    } elseif ($borrow_end < $borrow_start) {
        $error = '归还日期不能早于借用开始日期。';
    } else {
        // 确认设备存在且状态为「空闲」
        $dev = $pdo->prepare("SELECT id, name, status FROM devices WHERE id = ?");
        $dev->execute([$device_id]);
        $device = $dev->fetch();

        if (!$device) {
            $error = '设备不存在。';
        } elseif ($device['status'] !== '空闲') {
            $error = '该设备当前状态为「' . htmlspecialchars($device['status']) . '」，无法申请借用。';
        } else {
            // 冲突检查：同一设备是否有重叠的待审批/已批准记录
            $conflict = $pdo->prepare("
                SELECT id FROM device_borrows
                WHERE device_id = ?
                  AND status IN ('待审批', '已批准')
                  AND borrow_start <= ?
                  AND borrow_end   >= ?
            ");
            $conflict->execute([$device_id, $borrow_end, $borrow_start]);
            if ($conflict->fetch()) {
                $error = '该设备在所选日期范围内已有待审批或已批准的借用记录，请选择其他日期或设备。';
            } else {
                // 取得 employee_id（若用户有绑定）
                $emp_id = $me['employee_id'] ?? null;

                $pdo->prepare("
                    INSERT INTO device_borrows
                        (device_id, employee_id, purpose, borrow_start, borrow_end, status)
                    VALUES (?, ?, ?, ?, ?, '待审批')
                ")->execute([$device_id, $emp_id, $purpose, $borrow_start, $borrow_end]);
                write_audit_log('设备借用', '提交借用申请', "设备 ID {$device_id}（{$device['name']}）{$borrow_start}~{$borrow_end}：{$purpose}");
                $message = '借用申请已提交，请等待审批。';
            }
        }
    }
}

// ── 撤销自己的待审批申请 ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verify_csrf();
    $borrow_id = intval($_POST['borrow_id'] ?? 0);
    $emp_id    = $me['employee_id'] ?? null;

    // 只能撤销自己的、状态为「待审批」的申请
    $chk = $pdo->prepare("SELECT id FROM device_borrows WHERE id = ? AND employee_id = ? AND status = '待审批'");
    $chk->execute([$borrow_id, $emp_id]);
    if ($chk->fetch()) {
        $pdo->prepare("DELETE FROM device_borrows WHERE id = ?")->execute([$borrow_id]);
        write_audit_log('设备借用', '撤销借用申请', "撤销借用申请 ID {$borrow_id}");
        $message = '申请已撤销。';
    } else {
        $error = '无法撤销该申请（仅可撤销自己的待审批申请）。';
    }
}

// ── 空闲设备列表（申请表单用）───────────────────────────────────
$free_devices = $pdo->query("
    SELECT d.id, d.name, d.device_code, d.category, d.brand, d.model, dept.name AS dept_name
    FROM devices d
    LEFT JOIN departments dept ON d.department_id = dept.id
    WHERE d.status = '空闲'
    ORDER BY d.name ASC
")->fetchAll();

// ── 我的借用记录 ─────────────────────────────────────────────────
$emp_id = $me['employee_id'] ?? null;

if (has_role(['Admin', 'Manager'])) {
    // Admin / Manager 看全部
    $my_borrows = $pdo->query("
        SELECT b.*, d.name AS device_name, d.device_code,
               e.name AS employee_name,
               u.name AS approver_name
        FROM device_borrows b
        JOIN devices   d ON b.device_id    = d.id
        LEFT JOIN employees e ON b.employee_id  = e.id
        LEFT JOIN users     u ON b.approved_by  = u.id
        ORDER BY b.id DESC
        LIMIT 50
    ")->fetchAll();
    $list_title = '全部借用记录（最近 50 条）';
} else {
    // Employee 只看自己
    if ($emp_id) {
        $stmt = $pdo->prepare("
            SELECT b.*, d.name AS device_name, d.device_code,
                   e.name AS employee_name,
                   u.name AS approver_name
            FROM device_borrows b
            JOIN devices   d ON b.device_id    = d.id
            LEFT JOIN employees e ON b.employee_id  = e.id
            LEFT JOIN users     u ON b.approved_by  = u.id
            WHERE b.employee_id = ?
            ORDER BY b.id DESC
        ");
        $stmt->execute([$emp_id]);
        $my_borrows = $stmt->fetchAll();
    } else {
        $my_borrows = [];
    }
    $list_title = '我的借用记录';
}

$status_colors = [
    '待审批' => '#fd7e14',
    '已批准' => '#28a745',
    '已拒绝' => '#dc3545',
    '已归还' => '#6c757d',
];
?>

<div class="page-title">
    <h2>设备借用申请</h2>
    <p>申请借用空闲设备，提交后等待管理员审批。</p>
</div>

<?php if ($message): ?><div class="success"><?= safe($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<!-- 申请表单 -->
<section class="panel">
    <h2>新增借用申请</h2>
    <?php if ($free_devices): ?>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="apply">
        <div class="form-grid">
            <div style="grid-column:1/-1;">
                <label>选择设备 <span style="color:red">*</span></label>
                <select name="device_id" required>
                    <option value="">— 请选择空闲设备 —</option>
                    <?php foreach ($free_devices as $dv): ?>
                        <option value="<?= intval($dv['id']) ?>">
                            <?= safe($dv['name']) ?>
                            <?= $dv['device_code'] ? '（' . safe($dv['device_code']) . '）' : '' ?>
                            <?= $dv['brand'] ? '· ' . safe($dv['brand']) : '' ?>
                            <?= $dv['model'] ? safe($dv['model']) : '' ?>
                            <?= $dv['dept_name'] ? '· ' . safe($dv['dept_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>借用开始日期 <span style="color:red">*</span></label>
                <input type="date" name="borrow_start" required value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label>预计归还日期 <span style="color:red">*</span></label>
                <input type="date" name="borrow_end" required value="<?= date('Y-m-d') ?>">
            </div>
            <div style="grid-column:1/-1;">
                <label>借用用途 <span style="color:red">*</span></label>
                <textarea name="purpose" required rows="3"
                          placeholder="请说明借用原因和使用场合"></textarea>
            </div>
        </div>
        <button class="btn" type="submit" style="margin-top:10px;">提交申请</button>
    </form>
    <?php else: ?>
        <p style="color:#888;padding:12px 0;">目前没有空闲设备可以申请借用。</p>
    <?php endif; ?>
</section>

<!-- 借用记录 -->
<section class="panel" style="padding:0;">
    <div style="padding:16px 20px 8px;">
        <h2 style="margin:0;"><?= safe($list_title) ?></h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>设备</th>
                    <?php if (has_role(['Admin','Manager'])): ?><th>申请人</th><?php endif; ?>
                    <th>用途</th>
                    <th>借用日期</th>
                    <th>归还日期</th>
                    <th>状态</th>
                    <th>审批人</th>
                    <th>归还备注</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($my_borrows): ?>
                <?php foreach ($my_borrows as $b): ?>
                <tr>
                    <td><?= intval($b['id']) ?></td>
                    <td>
                        <strong><?= safe($b['device_name']) ?></strong>
                        <?php if ($b['device_code']): ?>
                            <br><small style="color:#888;"><?= safe($b['device_code']) ?></small>
                        <?php endif; ?>
                    </td>
                    <?php if (has_role(['Admin','Manager'])): ?>
                    <td><?= safe($b['employee_name'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td style="max-width:160px;"><?= safe($b['purpose']) ?></td>
                    <td style="white-space:nowrap;"><?= safe($b['borrow_start']) ?></td>
                    <td style="white-space:nowrap;"><?= safe($b['borrow_end']) ?></td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;
                                     font-size:12px;font-weight:bold;color:#fff;
                                     background:<?= $status_colors[$b['status']] ?? '#888' ?>;">
                            <?= safe($b['status']) ?>
                        </span>
                    </td>
                    <td><?= safe($b['approver_name'] ?? '—') ?></td>
                    <td style="font-size:12px;color:#888;"><?= safe($b['return_note'] ?? '—') ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($b['status'] === '待审批' && ($b['employee_id'] == $emp_id || has_role(['Admin','Manager']))): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('确定撤销此申请？')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="borrow_id" value="<?= intval($b['id']) ?>">
                                <button type="submit" class="btn-link" style="color:#dc3545;">撤销</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= has_role(['Admin','Manager']) ? 10 : 9 ?>"
                        style="text-align:center;color:#888;padding:32px;">
                        <?= $emp_id ? '暂无借用记录。' : '账号未绑定员工档案，无法查看借用记录。' ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
