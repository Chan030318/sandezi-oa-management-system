<?php
ob_start();

/* ─── Batch POST handler — must run before any HTML output ─── */
$batch_msg = '';
$batch_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_apply') {
    require_once __DIR__ . '/auth.php';
    require_login();
    require_role(['Admin', 'Manager']);
    verify_csrf();

    $emp_ids   = array_values(array_filter(array_map('intval', $_POST['employee_ids'] ?? [])));
    $shift_id  = intval($_POST['shift_id']  ?? 0);
    $date_from = trim($_POST['date_from']   ?? '');
    $date_to   = trim($_POST['date_to']     ?? '');
    $dept_id   = intval($_POST['dept_id']   ?? 0);

    if (empty($emp_ids)) {
        $batch_err = '请在排班表中勾选至少一名员工。';
    } elseif (!$shift_id) {
        $batch_err = '请选择班次。';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ||
              !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $batch_err = '日期格式无效。';
    } elseif ($date_from > $date_to) {
        $batch_err = '结束日期不能早于开始日期。';
    } else {
        $dates = [];
        $c = new DateTime($date_from);
        $e = new DateTime($date_to);
        while ($c <= $e) { $dates[] = $c->format('Y-m-d'); $c->modify('+1 day'); }

        if (count($dates) > 62) {
            $batch_err = '日期范围最多 62 天，请分批操作。';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO schedules (employee_id, shift_id, work_date, remark, source_leave_id)
                VALUES (?, ?, ?, '', NULL)
                ON DUPLICATE KEY UPDATE
                    shift_id        = VALUES(shift_id),
                    source_leave_id = NULL,
                    created_at      = CURRENT_TIMESTAMP
            ");
            $count = 0;
            foreach ($emp_ids as $eid) {
                foreach ($dates as $d) { $stmt->execute([$eid, $shift_id, $d]); $count++; }
            }

            $sn = $pdo->prepare("SELECT name FROM shifts WHERE id = ?");
            $sn->execute([$shift_id]);
            $shift_name = $sn->fetchColumn() ?: "ID {$shift_id}";

            $dept_name = '全部';
            if ($dept_id > 0) {
                $dn = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                $dn->execute([$dept_id]);
                $dept_name = $dn->fetchColumn() ?: "部门 {$dept_id}";
            }

            $emp_cnt  = count($emp_ids);
            $date_cnt = count($dates);
            write_audit_log('排班管理', '批量排班',
                "部门：{$dept_name}，员工数：{$emp_cnt}，" .
                "日期：{$date_from}~{$date_to}（{$date_cnt} 天），班次：{$shift_name}，共 {$count} 条");
            $batch_msg = "批量排班成功：{$emp_cnt} 人 × {$date_cnt} 天，共写入 {$count} 条记录。";
        }
    }
}

require_once __DIR__ . '/header.php';

/* ─── Helper functions ─── */
function getWeekDates($startDate = null) {
    $date = $startDate ? new DateTime($startDate) : new DateTime();
    $dow  = (int)$date->format('N');
    $date->modify('-' . ($dow - 1) . ' days');
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $d = clone $date; $d->modify("+{$i} days"); $week[] = $d;
    }
    return $week;
}

function shortTimeText($s, $e) {
    if (!$s || !$e) return '';
    if ($s === '00:00:00' && $e === '00:00:00') return ''; // system shift (leave/trip)
    return substr($s, 0, 5) . '-' . substr($e, 0, 5);
}

function shiftClass($name) {
    if (strpos($name, '主播早班') !== false) return 'shift-live-morning';
    if (strpos($name, '主播中班') !== false) return 'shift-live-mid';
    if (strpos($name, '主播晚班') !== false) return 'shift-live-night';
    if (strpos($name, '早班')     !== false) return 'shift-morning';
    if (strpos($name, '中班')     !== false) return 'shift-mid';
    if (strpos($name, '晚班')     !== false) return 'shift-night';
    if (strpos($name, '休')       !== false) return 'shift-off';
    return 'shift-default';
}

/* ─── Week dates ─── */
$startDate = $_GET['week'] ?? date('Y-m-d');
$weekDates = getWeekDates($startDate);
$weekStart = $weekDates[0]->format('Y-m-d');
$weekEnd   = $weekDates[6]->format('Y-m-d');
$prevWeek  = (new DateTime($weekStart))->modify('-7 days')->format('Y-m-d');
$nextWeek  = (new DateTime($weekStart))->modify('+7 days')->format('Y-m-d');
$weekNames = ['周一','周二','周三','周四','周五','周六','周日'];

/* ─── Data ─── */
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY id ASC")->fetchAll();
$shifts      = $pdo->query("SELECT * FROM shifts WHERE is_system = 0 ORDER BY id ASC")->fetchAll();

$employees = $pdo->query("
    SELECT e.id, e.name, e.position,
           COALESCE(e.department_id, 0) AS department_id,
           d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.department_id ASC, e.id ASC
")->fetchAll();

$sch = $pdo->prepare("
    SELECT s.employee_id, s.work_date, sh.name AS shift_name, sh.start_time, sh.end_time
    FROM schedules s
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.work_date BETWEEN ? AND ?
");
$sch->execute([$weekStart, $weekEnd]);
$scheduleMap = [];
foreach ($sch->fetchAll() as $row) {
    $scheduleMap[$row['employee_id']][$row['work_date']] = $row;
}

$canBatch = has_role(['Admin', 'Manager']);
$colCount = $canBatch ? 11 : 10;
?>

<style>
/* ── Existing shift colours ── */
.week-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
.week-toolbar .actions { display:flex; gap:8px; flex-wrap:wrap; }
.schedule-table-wrap { overflow-x:auto; }
.week-table { width:100%; border-collapse:collapse; min-width:1100px; background:#fff; }
.week-table th, .week-table td { border:1px solid #ddd; padding:9px 8px; text-align:center; vertical-align:middle; }
.week-table th { background:#f6f6f6; font-weight:700; }
.week-table td.info { background:#fafafa; font-weight:600; }
.week-table tr.row-selected td { background:#f0f4ff !important; }
.shift-badge { display:inline-block; min-width:80px; padding:5px 8px; border-radius:7px; font-size:12px; font-weight:700; }
.shift-time  { display:block; margin-top:3px; font-size:10px; opacity:.8; }
.empty-shift { color:#ccc; }
.shift-morning     { background:#dff3e6; color:#16703a; }
.shift-mid         { background:#e6f0ff; color:#185ea8; }
.shift-night       { background:#ffe1c7; color:#b65300; }
.shift-live-morning{ background:#eadcff; color:#6a1b9a; }
.shift-live-mid    { background:#fff0c2; color:#8a5a00; }
.shift-live-night  { background:#ffd6e8; color:#a0004f; }
.shift-off         { background:#dff5e8; color:#128047; }
.shift-default     { background:#eee;    color:#333;    }

/* ── Batch toolbar ── */
.btb { display:flex; flex-direction:column; gap:10px; }
.btb-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.btb-label { font-size:13px; font-weight:600; color:#555; white-space:nowrap; }
.btb-sep   { color:#888; padding:0 2px; }
.btb-select, .btb-date {
    border:1px solid #ddd; border-radius:8px; padding:8px 10px;
    font-size:13px; font-family:inherit; background:#fff;
}
.btb-select { min-width:140px; }
.btb-date   { width:136px; }
.btb-status { font-size:13px; font-weight:700; color:#5c7cfa; white-space:nowrap; margin-left:4px; }
</style>

<div class="page-title">
    <h2>周排班表</h2>
    <p>查看本周排班，管理员可直接在表格内勾选员工批量套用班次。</p>
</div>

<?php if ($batch_msg): ?>
    <div class="success"><?= safe($batch_msg) ?></div>
<?php endif; ?>
<?php if ($batch_err): ?>
    <div class="alert"><?= safe($batch_err) ?></div>
<?php endif; ?>

<form method="POST" id="batch-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action"  value="batch_apply">
    <input type="hidden" name="dept_id" id="dept_id_field" value="0">

    <!-- ── Batch Toolbar (Admin / Manager only) ── -->
    <?php if ($canBatch): ?>
    <section class="panel">
        <h2 style="margin-top:0;margin-bottom:14px;font-size:16px;">批量套用班次</h2>
        <div class="btb">
            <!-- Row 1: Dept filter -->
            <div class="btb-row">
                <span class="btb-label">部门</span>
                <select id="dept-select" class="btb-select" onchange="filterByDept(this.value)">
                    <option value="0">全部部门</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= intval($dept['id']) ?>"><?= safe($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn secondary" onclick="selectAllDept()">全选本部门</button>
                <button type="button" class="btn secondary" onclick="clearAll()">清空选择</button>
                <span class="btb-status" id="batch-status">未选择员工</span>
            </div>
            <!-- Row 2: Date range + shift + apply -->
            <div class="btb-row">
                <span class="btb-label">套用日期</span>
                <input type="date" name="date_from" id="date_from" value="<?= safe($weekStart) ?>" class="btb-date">
                <span class="btb-sep">至</span>
                <input type="date" name="date_to"   id="date_to"   value="<?= safe($weekEnd) ?>"   class="btb-date">
                <span class="btb-label" style="margin-left:6px;">班次</span>
                <select name="shift_id" id="shift_select" class="btb-select" style="min-width:170px;">
                    <option value="">请选择班次</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?= intval($s['id']) ?>">
                            <?= safe($s['name']) ?>（<?= safe(substr($s['start_time'],0,5)) ?>-<?= safe(substr($s['end_time'],0,5)) ?>）
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn" id="apply-btn" onclick="applyBatch()" disabled>
                    套用到已选员工
                </button>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── Weekly Table ── -->
    <section class="panel">
        <div class="week-toolbar">
            <h2 style="margin:0;">排班表 <?= safe($weekStart) ?> ~ <?= safe($weekEnd) ?></h2>
            <div class="actions">
                <a class="btn secondary" href="weekly_schedule.php?week=<?= safe($prevWeek) ?>">← 上周</a>
                <a class="btn"           href="weekly_schedule.php">本周</a>
                <a class="btn secondary" href="weekly_schedule.php?week=<?= safe($nextWeek) ?>">下周 →</a>
                <a class="btn secondary" href="schedule.php">单条排班</a>
            </div>
        </div>

        <div class="schedule-table-wrap">
            <table class="week-table">
                <thead>
                    <tr>
                        <?php if ($canBatch): ?>
                        <th style="width:36px;">
                            <input type="checkbox" id="master-cb" title="全选 / 全不选"
                                   style="width:16px;height:16px;cursor:pointer;"
                                   onchange="masterToggle(this.checked)">
                        </th>
                        <?php endif; ?>
                        <th>部门</th>
                        <th>岗位</th>
                        <th>姓名</th>
                        <?php foreach ($weekDates as $i => $date): ?>
                            <th>
                                <?= safe($date->format('m.d')) ?><br>
                                <span style="font-weight:400;font-size:11px;"><?= safe($weekNames[$i]) ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="<?= $colCount ?>" style="text-align:center;color:#999;padding:24px;">
                            暂无员工资料
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr class="emp-row" data-dept="<?= intval($emp['department_id']) ?>" id="row-<?= intval($emp['id']) ?>">
                        <?php if ($canBatch): ?>
                        <td style="text-align:center;">
                            <input type="checkbox"
                                   name="employee_ids[]"
                                   value="<?= intval($emp['id']) ?>"
                                   class="emp-cb"
                                   data-dept="<?= intval($emp['department_id']) ?>"
                                   style="width:15px;height:15px;cursor:pointer;"
                                   onchange="onCbChange(this)">
                        </td>
                        <?php endif; ?>
                        <td class="info"><?= safe($emp['department_name'] ?? '-') ?></td>
                        <td class="info"><?= safe($emp['position']        ?? '-') ?></td>
                        <td class="info" style="white-space:nowrap;"><?= safe($emp['name']) ?></td>

                        <?php foreach ($weekDates as $date): ?>
                            <?php
                                $day   = $date->format('Y-m-d');
                                $shift = $scheduleMap[$emp['id']][$day] ?? null;
                            ?>
                            <td>
                                <?php if ($shift): ?>
                                    <span class="shift-badge <?= safe(shiftClass($shift['shift_name'])) ?>">
                                        <?= safe($shift['shift_name']) ?>
                                        <span class="shift-time"><?= safe(shortTimeText($shift['start_time'], $shift['end_time'])) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="empty-shift">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</form>

<?php if ($canBatch): ?>
<script>
(function () {
    var currentDept = 0;

    function visibleCbs() {
        return Array.from(document.querySelectorAll('.emp-cb')).filter(function (cb) {
            return cb.closest('tr').style.display !== 'none';
        });
    }

    window.updateCount = function () {
        var n   = document.querySelectorAll('.emp-cb:checked').length;
        var el  = document.getElementById('batch-status');
        var btn = document.getElementById('apply-btn');
        if (el)  el.textContent  = n > 0 ? '已选 ' + n + ' 人' : '未选择员工';
        if (btn) btn.disabled    = (n === 0);

        var master = document.getElementById('master-cb');
        if (master) {
            var vis = visibleCbs();
            var chk = vis.filter(function (cb) { return cb.checked; });
            master.checked       = vis.length > 0 && chk.length === vis.length;
            master.indeterminate = chk.length > 0 && chk.length < vis.length;
        }
    };

    window.onCbChange = function (cb) {
        var row = document.getElementById('row-' + cb.value);
        if (row) row.classList.toggle('row-selected', cb.checked);
        updateCount();
    };

    window.masterToggle = function (on) {
        visibleCbs().forEach(function (cb) {
            cb.checked = on;
            var row = document.getElementById('row-' + cb.value);
            if (row) row.classList.toggle('row-selected', on);
        });
        updateCount();
    };

    window.filterByDept = function (deptId) {
        currentDept = parseInt(deptId) || 0;
        document.getElementById('dept_id_field').value = currentDept;
        document.querySelectorAll('.emp-row').forEach(function (row) {
            var show = currentDept === 0 || parseInt(row.dataset.dept) === currentDept;
            row.style.display = show ? '' : 'none';
        });
        updateCount();
    };

    window.selectAllDept = function () {
        visibleCbs().forEach(function (cb) {
            cb.checked = true;
            var row = document.getElementById('row-' + cb.value);
            if (row) row.classList.add('row-selected');
        });
        updateCount();
    };

    window.clearAll = function () {
        document.querySelectorAll('.emp-cb').forEach(function (cb) {
            cb.checked = false;
            var row = document.getElementById('row-' + cb.value);
            if (row) row.classList.remove('row-selected');
        });
        updateCount();
    };

    window.applyBatch = function () {
        var empCount = document.querySelectorAll('.emp-cb:checked').length;
        if (empCount === 0) { alert('请先勾选员工'); return; }

        var shiftEl   = document.getElementById('shift_select');
        var shiftText = shiftEl.selectedIndex >= 0 ? shiftEl.options[shiftEl.selectedIndex].text : '';
        if (!shiftEl.value) { alert('请选择班次'); return; }

        var dateFrom = document.getElementById('date_from').value;
        var dateTo   = document.getElementById('date_to').value;
        if (!dateFrom || !dateTo) { alert('请填写日期范围'); return; }
        if (dateFrom > dateTo)    { alert('结束日期不能早于开始日期'); return; }

        var ms   = new Date(dateFrom + 'T00:00:00') - 0;
        var me   = new Date(dateTo   + 'T00:00:00') - 0;
        var days = Math.round((me - ms) / 86400000) + 1;
        if (days > 62) { alert('日期范围最多 62 天，请分批操作'); return; }

        var total = empCount * days;
        var ok = window.confirm(
            '即将修改：\n' +
            '  员工：'    + empCount + ' 人\n' +
            '  日期：'    + dateFrom + ' 至 ' + dateTo + '（' + days + ' 天）\n' +
            '  班次：'    + shiftText + '\n' +
            '  预计生成：' + total + ' 条排班记录\n\n' +
            '已有排班将被覆盖，是否继续？'
        );
        if (ok) document.getElementById('batch-form').submit();
    };

    updateCount();
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
