<?php
ob_start();

$batch_message = '';
$batch_error   = '';

// POST handler runs FIRST — before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_schedule') {
    require_once __DIR__ . '/auth.php';
    require_login();
    require_role(['Admin', 'Manager']);
    verify_csrf();

    $emp_ids   = array_values(array_filter(array_map('intval', $_POST['employee_ids'] ?? [])));
    $shift_id  = intval($_POST['shift_id'] ?? 0);
    $date_from = trim($_POST['date_from'] ?? '');
    $date_to   = trim($_POST['date_to']   ?? '');
    $remark    = trim($_POST['remark']    ?? '');

    if (empty($emp_ids)) {
        $batch_error = '请至少选择一名员工。';
    } elseif (!$shift_id) {
        $batch_error = '请选择班次。';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ||
              !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $batch_error = '日期格式无效。';
    } elseif ($date_from > $date_to) {
        $batch_error = '结束日期不能早于开始日期。';
    } else {
        $dates = [];
        $cur   = new DateTime($date_from);
        $end   = new DateTime($date_to);
        while ($cur <= $end) {
            $dates[] = $cur->format('Y-m-d');
            $cur->modify('+1 day');
        }
        if (count($dates) > 31) {
            $batch_error = '日期范围最多 31 天，请分批操作。';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO schedules (employee_id, shift_id, work_date, remark)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    shift_id   = VALUES(shift_id),
                    remark     = VALUES(remark),
                    created_at = CURRENT_TIMESTAMP
            ");
            $count = 0;
            foreach ($emp_ids as $eid) {
                foreach ($dates as $d) {
                    $stmt->execute([$eid, $shift_id, $d, $remark]);
                    $count++;
                }
            }
            $emp_cnt  = count($emp_ids);
            $date_cnt = count($dates);
            write_audit_log('排班管理', '批量排班',
                "批量排班 {$emp_cnt} 人 × {$date_cnt} 天（{$date_from}~{$date_to}），班次 ID {$shift_id}");
            $batch_message = "批量排班成功，共写入 {$count} 条记录（{$emp_cnt} 人 × {$date_cnt} 天）。";
        }
    }
}

require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY id ASC")->fetchAll();
$employees   = $pdo->query("
    SELECT e.id, e.name, e.position,
           COALESCE(e.department_id, 0) AS department_id,
           d.name AS dept_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.department_id ASC, e.name ASC
")->fetchAll();
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY id ASC")->fetchAll();

$emp_by_dept = [];
foreach ($employees as $e) {
    $emp_by_dept[(int)$e['department_id']][] = $e;
}

$today    = new DateTime();
$dow      = (int)$today->format('N');
$monday   = (clone $today)->modify('-' . ($dow - 1) . ' days');
$sunday   = (clone $monday)->modify('+6 days');
$def_from  = $_POST['date_from'] ?? $monday->format('Y-m-d');
$def_to    = $_POST['date_to']   ?? $sunday->format('Y-m-d');
$def_shift = intval($_POST['shift_id'] ?? 0);
$def_remark = $_POST['remark'] ?? '';

function shortT($t) { return $t ? substr($t, 0, 5) : ''; }
?>

<style>
.emp-label {
    display:flex;align-items:center;gap:8px;
    padding:8px 14px;
    border:1px solid #e0e0e0;border-radius:8px;
    cursor:pointer;background:#fff;
    user-select:none;transition:background .1s,border-color .1s;
}
.emp-label:hover { background:#f5f7ff; border-color:#aab4f0; }
.emp-label.checked { background:#eef2ff; border-color:#5c7cfa; }
.dept-header-row {
    display:flex;align-items:center;gap:10px;
    padding:9px 14px;background:#f2f4f8;
    border-radius:8px;margin-bottom:10px;cursor:pointer;
}
.sticky-bar {
    position:sticky;bottom:0;
    background:#fff;border-top:2px solid #e0e0e0;
    padding:14px 20px;display:flex;align-items:center;gap:16px;
    z-index:20;box-shadow:0 -2px 8px rgba(0,0,0,.06);
}
</style>

<div class="page-title">
    <h2>批量排班</h2>
    <p>选择员工（支持部门全选）、日期范围和班次，一键批量录入。</p>
</div>

<?php if ($batch_message): ?>
    <div class="success"><?= safe($batch_message) ?></div>
<?php endif; ?>
<?php if ($batch_error): ?>
    <div class="alert"><?= safe($batch_error) ?></div>
<?php endif; ?>

<form method="POST" id="batch-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="batch_schedule">

    <!-- 排班设置 -->
    <section class="panel">
        <h2>排班设置</h2>
        <div class="form-grid">
            <div>
                <label>开始日期 <span style="color:red">*</span></label>
                <input type="date" name="date_from" required value="<?= safe($def_from) ?>">
            </div>
            <div>
                <label>结束日期 <span style="color:red">*</span></label>
                <input type="date" name="date_to" required value="<?= safe($def_to) ?>">
            </div>
            <div>
                <label>班次 <span style="color:red">*</span></label>
                <select name="shift_id" required>
                    <option value="">请选择班次</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?= intval($s['id']) ?>"
                                <?= $def_shift === intval($s['id']) ? 'selected' : '' ?>>
                            <?= safe($s['name']) ?>（<?= safe(shortT($s['start_time'])) ?>-<?= safe(shortT($s['end_time'])) ?>）
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>备注（选填）</label>
                <input type="text" name="remark" placeholder="例如：临时调班"
                       value="<?= safe($def_remark) ?>">
            </div>
        </div>
    </section>

    <!-- 员工选择 -->
    <section class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h2 style="margin:0;">选择员工</h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" class="btn secondary" id="btn-all">全选</button>
                <button type="button" class="btn secondary" id="btn-none">全不选</button>
                <span style="color:#666;font-size:14px;">已选 <strong id="cnt-top">0</strong> 人</span>
            </div>
        </div>

        <?php if (empty($employees)): ?>
            <p style="color:#999;">暂无在职员工。</p>
        <?php else: ?>
            <?php foreach ($departments as $dept): ?>
                <?php $emps = $emp_by_dept[$dept['id']] ?? []; ?>
                <?php if (empty($emps)) continue; ?>
                <div style="margin-bottom:22px;">
                    <div class="dept-header-row">
                        <input type="checkbox" class="dept-master"
                               data-dept="<?= intval($dept['id']) ?>"
                               style="width:16px;height:16px;cursor:pointer;">
                        <strong><?= safe($dept['name']) ?></strong>
                        <span style="color:#888;font-size:13px;"><?= count($emps) ?> 人</span>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;padding-left:14px;">
                        <?php foreach ($emps as $e): ?>
                            <label class="emp-label" id="lbl-<?= intval($e['id']) ?>">
                                <input type="checkbox" name="employee_ids[]"
                                       value="<?= intval($e['id']) ?>"
                                       class="emp-check"
                                       data-dept="<?= intval($e['department_id']) ?>"
                                       style="width:15px;height:15px;">
                                <span><?= safe($e['name']) ?></span>
                                <small style="color:#888;"><?= safe($e['position']) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($emp_by_dept[0])): ?>
                <div style="margin-bottom:22px;">
                    <div class="dept-header-row" style="background:#fafafa;">
                        <input type="checkbox" class="dept-master" data-dept="0"
                               style="width:16px;height:16px;cursor:pointer;">
                        <strong>未分配部门</strong>
                        <span style="color:#888;font-size:13px;"><?= count($emp_by_dept[0]) ?> 人</span>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;padding-left:14px;">
                        <?php foreach ($emp_by_dept[0] as $e): ?>
                            <label class="emp-label" id="lbl-<?= intval($e['id']) ?>">
                                <input type="checkbox" name="employee_ids[]"
                                       value="<?= intval($e['id']) ?>"
                                       class="emp-check" data-dept="0"
                                       style="width:15px;height:15px;">
                                <span><?= safe($e['name']) ?></span>
                                <small style="color:#888;"><?= safe($e['position']) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- 固定底栏 -->
    <div class="sticky-bar">
        <button class="btn" type="submit" id="submit-btn" disabled>
            ✅ 批量应用排班
        </button>
        <span style="color:#555;font-size:14px;">已选 <strong id="cnt-bottom">0</strong> 人</span>
        <a class="btn secondary" href="weekly_schedule.php">查看排班表</a>
        <a class="btn secondary" href="schedule.php">单条排班</a>
    </div>
</form>

<script>
(function () {
    function setLabel(ec, on) {
        var lbl = document.getElementById('lbl-' + ec.value);
        if (!lbl) return;
        if (on) { lbl.classList.add('checked'); }
        else    { lbl.classList.remove('checked'); }
    }

    function updateCount() {
        var n = document.querySelectorAll('.emp-check:checked').length;
        document.getElementById('cnt-top').textContent    = n;
        document.getElementById('cnt-bottom').textContent = n;
        document.getElementById('submit-btn').disabled    = (n === 0);

        document.querySelectorAll('.dept-master').forEach(function (dm) {
            var dept   = dm.dataset.dept;
            var all    = document.querySelectorAll('.emp-check[data-dept="' + dept + '"]');
            var chkd   = document.querySelectorAll('.emp-check[data-dept="' + dept + '"]:checked');
            dm.checked       = all.length > 0 && all.length === chkd.length;
            dm.indeterminate = chkd.length > 0 && chkd.length < all.length;
        });
    }

    // Department master toggle
    document.querySelectorAll('.dept-master').forEach(function (dm) {
        dm.addEventListener('change', function () {
            var dept = this.dataset.dept;
            var on   = this.checked;
            document.querySelectorAll('.emp-check[data-dept="' + dept + '"]').forEach(function (ec) {
                ec.checked = on;
                setLabel(ec, on);
            });
            updateCount();
        });
    });

    // Individual checkbox
    document.querySelectorAll('.emp-check').forEach(function (ec) {
        ec.addEventListener('change', function () {
            setLabel(this, this.checked);
            updateCount();
        });
    });

    // Global select all
    document.getElementById('btn-all').addEventListener('click', function () {
        document.querySelectorAll('.emp-check').forEach(function (ec) {
            ec.checked = true; setLabel(ec, true);
        });
        document.querySelectorAll('.dept-master').forEach(function (dm) {
            dm.checked = true; dm.indeterminate = false;
        });
        updateCount();
    });

    // Global deselect all
    document.getElementById('btn-none').addEventListener('click', function () {
        document.querySelectorAll('.emp-check').forEach(function (ec) {
            ec.checked = false; setLabel(ec, false);
        });
        document.querySelectorAll('.dept-master').forEach(function (dm) {
            dm.checked = false; dm.indeterminate = false;
        });
        updateCount();
    });

    updateCount();
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
