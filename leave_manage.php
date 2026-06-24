<?php
ob_start();
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

/* ─── Helpers ─── */

function getSystemShiftId($pdo, $typeName) {
    $s = $pdo->prepare("SELECT id FROM shifts WHERE name = ? AND is_system = 1 LIMIT 1");
    $s->execute([$typeName]);
    return $s->fetchColumn() ?: null;
}

function syncLeaveToSchedules($pdo, $leaveId, $empId, $leaveType, $startDate, $endDate) {
    $shiftId = getSystemShiftId($pdo, $leaveType);
    if (!$shiftId) return ['count' => 0, 'json' => null]; // no system shift for this type

    // Build date list
    $dates = [];
    $cur   = new DateTime($startDate);
    $end   = new DateTime($endDate);
    while ($cur <= $end) { $dates[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }
    if (empty($dates)) return ['count' => 0, 'json' => null];

    // Capture existing MANUAL schedule rows for reversal
    $ph   = implode(',', array_fill(0, count($dates), '?'));
    $orig = $pdo->prepare("
        SELECT work_date, shift_id, remark FROM schedules
        WHERE employee_id = ? AND work_date IN ({$ph})
          AND (source_leave_id IS NULL OR source_leave_id != ?)
    ");
    $orig->execute(array_merge([$empId], $dates, [$leaveId]));
    $originals = [];
    foreach ($orig->fetchAll() as $row) {
        $originals[$row['work_date']] = [
            'shift_id' => intval($row['shift_id']),
            'remark'   => $row['remark'] ?? '',
        ];
    }

    // UPSERT leave schedules
    $ins = $pdo->prepare("
        INSERT INTO schedules (employee_id, shift_id, work_date, remark, source_leave_id)
        VALUES (?, ?, ?, '', ?)
        ON DUPLICATE KEY UPDATE
            shift_id        = VALUES(shift_id),
            remark          = VALUES(remark),
            source_leave_id = VALUES(source_leave_id),
            created_at      = CURRENT_TIMESTAMP
    ");
    $count = 0;
    foreach ($dates as $d) {
        $ins->execute([$empId, $shiftId, $d, $leaveId]);
        $count++;
    }

    return [
        'count' => $count,
        'json'  => empty($originals) ? null : json_encode($originals, JSON_UNESCAPED_UNICODE),
    ];
}

function revokeLeaveSchedules($pdo, $leaveId, $empId, $originalJson) {
    // Remove leave-sourced schedule rows
    $pdo->prepare("DELETE FROM schedules WHERE source_leave_id = ? AND employee_id = ?")
        ->execute([$leaveId, $empId]);

    // Restore original schedules
    $restored = 0;
    if ($originalJson) {
        $originals = json_decode($originalJson, true) ?: [];
        $ins = $pdo->prepare("
            INSERT INTO schedules (employee_id, shift_id, work_date, remark, source_leave_id)
            VALUES (?, ?, ?, ?, NULL)
            ON DUPLICATE KEY UPDATE
                shift_id        = VALUES(shift_id),
                remark          = VALUES(remark),
                source_leave_id = NULL,
                created_at      = CURRENT_TIMESTAMP
        ");
        foreach ($originals as $date => $data) {
            $ins->execute([$empId, intval($data['shift_id']), $date, $data['remark'] ?? '']);
            $restored++;
        }
    }
    return $restored;
}

/* ─── POST Handler ─── */
$message     = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    $leaveId = intval($_POST['leave_id'] ?? 0);

    if ($leaveId) {
        $lv = $pdo->prepare("
            SELECT l.*, e.name AS emp_name
            FROM leaves l
            LEFT JOIN employees e ON l.employee_id = e.id
            WHERE l.id = ?
        ");
        $lv->execute([$leaveId]);
        $leave = $lv->fetch();

        if ($leave) {
            $empId     = intval($leave['employee_id']);
            $lvType    = $leave['leave_type'];
            $startDate = $leave['start_date'];
            $endDate   = $leave['end_date'];
            $empName   = $leave['emp_name'] ?? "员工 {$empId}";
            $lvDesc    = "{$empName} 【{$lvType}】 {$startDate}~{$endDate}";
            $action    = $_POST['action'];
            $me        = current_user();
            $meId      = intval($me['id'] ?? 0);

            if ($action === 'approve' && $leave['status'] === 'Pending') {
                $result    = syncLeaveToSchedules($pdo, $leaveId, $empId, $lvType, $startDate, $endDate);
                $syncCount = $result['count'];
                $origJson  = $result['json'];

                $pdo->prepare("
                    UPDATE leaves
                    SET status                  = 'Approved',
                        approve_remark          = '已批准',
                        original_schedules_json = ?,
                        approved_by             = ?,
                        approved_at             = CURRENT_TIMESTAMP
                    WHERE id = ?
                ")->execute([$origJson, $meId, $leaveId]);

                write_audit_log('请假管理', '审批通过', "批准请假：{$lvDesc}");
                if ($syncCount > 0) {
                    write_audit_log('请假管理', '同步排班',
                        "请假审批同步排班：{$lvDesc}，共 {$syncCount} 天");
                }
                $message = "请假已批准" .
                    ($syncCount > 0
                        ? "，已自动写入排班 {$syncCount} 天"
                        : "（班次 [{$lvType}] 尚未配置系统班次，排班未同步）");

            } elseif ($action === 'reject' && $leave['status'] === 'Pending') {
                $rejectReason = trim($_POST['reject_reason'] ?? '');
                $pdo->prepare("
                    UPDATE leaves SET status='Rejected', approve_remark=? WHERE id=?
                ")->execute([$rejectReason ?: '已拒绝', $leaveId]);
                write_audit_log('请假管理', '审批拒绝',
                    "拒绝请假：{$lvDesc}" . ($rejectReason ? "，原因：{$rejectReason}" : ''));
                $message     = '请假申请已拒绝';
                $messageType = 'error';

            } elseif ($action === 'revoke' && $leave['status'] === 'Approved') {
                $restored = revokeLeaveSchedules($pdo, $leaveId, $empId, $leave['original_schedules_json'] ?? null);
                $pdo->prepare("
                    UPDATE leaves SET status='Revoked', approve_remark='审批已撤销' WHERE id=?
                ")->execute([$leaveId]);
                write_audit_log('请假管理', '撤销审批',
                    "撤销请假审批：{$lvDesc}，" .
                    "已移除排班，恢复原排班 {$restored} 条");
                $message = "审批已撤销，对应排班已移除" .
                    ($restored > 0 ? "，已恢复 {$restored} 条原排班" : "");

            } else {
                $message     = '操作无效或当前状态不允许此操作';
                $messageType = 'error';
            }
        } else {
            $message     = '找不到该申请记录';
            $messageType = 'error';
        }
    }
}

/* ─── Query ─── */
$filter  = $_GET['filter'] ?? 'pending';
$where   = '';
if ($filter === 'pending')  $where = "WHERE l.status = 'Pending'";
if ($filter === 'approved') $where = "WHERE l.status = 'Approved'";
if ($filter === 'rejected') $where = "WHERE l.status IN ('Rejected','Revoked')";

$leaves = $pdo->query("
    SELECT l.*, e.name AS employee_name, e.position, d.name AS department_name
    FROM leaves l
    JOIN employees e ON l.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    {$where}
    ORDER BY
        FIELD(l.status,'Pending','Approved','Rejected','Revoked'),
        l.created_at DESC
")->fetchAll();

/* ─── Display helpers ─── */
function statusBadge($status) {
    $map = [
        'Pending'  => ['待审批', '#fff3e0', '#e65100'],
        'Approved' => ['已批准', '#e8f5e9', '#2e7d32'],
        'Rejected' => ['已拒绝', '#ffebee', '#b71c1c'],
        'Revoked'  => ['已撤销', '#f5f5f5', '#616161'],
    ];
    [$text, $bg, $clr] = $map[$status] ?? [$status, '#eee', '#333'];
    return "<span style='background:{$bg};color:{$clr};padding:3px 10px;"
         . "border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;'>"
         . htmlspecialchars($text, ENT_QUOTES) . "</span>";
}

function typeBadge($type) {
    $trips = ['出差'];
    $outs  = ['外出'];
    $comps = ['调休'];
    if (in_array($type, $trips)) $style = 'background:#e3f2fd;color:#0d47a1;';
    elseif (in_array($type, $outs))  $style = 'background:#f3e5f5;color:#6a1b9a;';
    elseif (in_array($type, $comps)) $style = 'background:#e8f5e9;color:#1b5e20;';
    else                              $style = 'background:#fff3e0;color:#bf360c;';
    return "<span style='{$style}padding:2px 9px;border-radius:5px;font-size:12px;font-weight:600;'>"
         . htmlspecialchars($type, ENT_QUOTES) . "</span>";
}
?>

<div class="page-title">
    <h2>请假 / 出差 / 外出审批</h2>
    <p>审批通过后自动写入周排班表，撤销审批可恢复原排班。</p>
</div>

<?php if ($message): ?>
    <div class="<?= $messageType === 'error' ? 'alert' : 'success' ?>"><?= safe($message) ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head" style="flex-wrap:wrap;gap:8px;">
        <h2 style="margin:0;">申请列表</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn <?= $filter==='pending'  ? '' : 'secondary' ?>" href="?filter=pending">待审批</a>
            <a class="btn <?= $filter==='approved' ? '' : 'secondary' ?>" href="?filter=approved">已批准</a>
            <a class="btn <?= $filter==='rejected' ? '' : 'secondary' ?>" href="?filter=rejected">已拒绝/撤销</a>
            <a class="btn <?= $filter==='all'      ? '' : 'secondary' ?>" href="?filter=all">全部</a>
            <a class="btn secondary" href="leave_apply.php">新增申请</a>
        </div>
    </div>

    <div style="overflow-x:auto;margin-top:16px;">
    <table>
        <thead>
        <tr>
            <th>员工</th><th>部门</th><th>类型</th>
            <th>开始</th><th>结束</th><th>天数</th>
            <th>原因</th><th>状态</th><th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($leaves)): ?>
            <tr><td colspan="9" style="text-align:center;color:#999;padding:20px;">暂无申请记录</td></tr>
        <?php else: ?>
            <?php foreach ($leaves as $l): ?>
            <?php
                $d1   = new DateTime($l['start_date']);
                $d2   = new DateTime($l['end_date']);
                $days = $d1->diff($d2)->days + 1;
            ?>
            <tr>
                <td><?= safe($l['employee_name']) ?></td>
                <td><?= safe($l['department_name'] ?? '-') ?></td>
                <td><?= typeBadge($l['leave_type']) ?></td>
                <td><?= safe($l['start_date']) ?></td>
                <td><?= safe($l['end_date']) ?></td>
                <td><?= $days ?> 天</td>
                <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= safe($l['reason'] ?? '') ?>">
                    <?= safe($l['reason'] ?: '-') ?>
                </td>
                <td><?= statusBadge($l['status']) ?></td>
                <td style="white-space:nowrap;">
                    <?php if ($l['status'] === 'Pending'): ?>
                        <!-- 批准 -->
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="leave_id" value="<?= intval($l['id']) ?>">
                            <input type="hidden" name="action"   value="approve">
                            <button type="submit" class="btn-link"
                                    style="color:#2e7d32;font-weight:700;">批准</button>
                        </form>
                        |
                        <!-- 拒绝 -->
                        <form method="POST" style="display:inline;"
                              onsubmit="return handleReject(this)">
                            <?= csrf_field() ?>
                            <input type="hidden" name="leave_id"      value="<?= intval($l['id']) ?>">
                            <input type="hidden" name="action"        value="reject">
                            <input type="hidden" name="reject_reason" class="rr" value="">
                            <button type="submit" class="btn-link"
                                    style="color:#b71c1c;">拒绝</button>
                        </form>

                    <?php elseif ($l['status'] === 'Approved'): ?>
                        <!-- 撤销 -->
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('确定撤销此审批？对应排班将被移除，原排班将恢复。')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="leave_id" value="<?= intval($l['id']) ?>">
                            <input type="hidden" name="action"   value="revoke">
                            <button type="submit" class="btn-link"
                                    style="color:#e65100;">撤销审批</button>
                        </form>

                    <?php else: ?>
                        <span style="color:#999;font-size:12px;">
                            <?= safe($l['approve_remark'] ?? '-') ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<script>
function handleReject(form) {
    var reason = prompt('请输入拒绝原因（可留空直接确认）：', '');
    if (reason === null) return false;
    form.querySelector('.rr').value = reason;
    return true;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
