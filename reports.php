<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

// 默认日期范围：当月
$default_start = date('Y-m-01');
$default_end   = date('Y-m-d');

$date_from = $_GET['date_from'] ?? $default_start;
$date_to   = $_GET['date_to']   ?? $default_end;
$type      = $_GET['type']      ?? '';   // 'schedule' | 'leave'

// 基础校验
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = $default_start;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = $default_end;
if ($date_from > $date_to) [$date_from, $date_to] = [$date_to, $date_from];

// ── CSV 输出辅助 ────────────────────────────────────────────────
function output_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM — 避免 Excel 中文乱码
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ── 导出：排班记录 ──────────────────────────────────────────────
if ($type === 'schedule') {
    $stmt = $pdo->prepare("
        SELECT s.work_date,
               e.name          AS employee_name,
               d.name          AS department_name,
               e.position,
               sh.name         AS shift_name,
               sh.start_time,
               sh.end_time,
               s.remark,
               u.name          AS created_by_name,
               s.created_at
        FROM schedules s
        JOIN employees   e  ON s.employee_id = e.id
        LEFT JOIN departments d  ON e.department_id = d.id
        JOIN shifts      sh ON s.shift_id    = sh.id
        LEFT JOIN users  u  ON s.created_by  = u.id
        WHERE s.work_date BETWEEN ? AND ?
        ORDER BY s.work_date ASC, e.name ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $csv_rows = [];
    foreach ($rows as $r) {
        $csv_rows[] = [
            $r['work_date'],
            $r['employee_name'],
            $r['department_name'] ?? '',
            $r['position'],
            $r['shift_name'],
            $r['start_time'],
            $r['end_time'],
            $r['remark'] ?? '',
            $r['created_by_name'] ?? '',
            $r['created_at'],
        ];
    }

    $filename = '排班记录_' . $date_from . '_至_' . $date_to . '.csv';
    output_csv($filename, ['日期','员工姓名','部门','职位','班次','开始时间','结束时间','备注','创建人','创建时间'], $csv_rows);
}

// ── 导出：请假记录 ──────────────────────────────────────────────
if ($type === 'leave') {
    $stmt = $pdo->prepare("
        SELECT l.start_date,
               l.end_date,
               e.name          AS employee_name,
               d.name          AS department_name,
               e.position,
               l.leave_type,
               l.reason,
               l.status,
               l.approve_remark,
               l.created_at
        FROM leaves l
        JOIN employees   e ON l.employee_id    = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE l.start_date <= ? AND l.end_date >= ?
        ORDER BY l.start_date ASC, e.name ASC
    ");
    $stmt->execute([$date_to, $date_from]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $status_map = ['Pending' => '待审核', 'Approved' => '已批准', 'Rejected' => '已拒绝'];

    $csv_rows = [];
    foreach ($rows as $r) {
        $csv_rows[] = [
            $r['start_date'],
            $r['end_date'],
            $r['employee_name'],
            $r['department_name'] ?? '',
            $r['position'],
            $r['leave_type'],
            $r['reason'] ?? '',
            $status_map[$r['status']] ?? $r['status'],
            $r['approve_remark'] ?? '',
            $r['created_at'],
        ];
    }

    $filename = '请假记录_' . $date_from . '_至_' . $date_to . '.csv';
    output_csv($filename, ['开始日期','结束日期','员工姓名','部门','职位','假期类型','请假原因','审批状态','审批备注','申请时间'], $csv_rows);
}

// ── 报表选择页面（无 type 参数） ────────────────────────────────

// 预览：排班条数
$cnt_schedule = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE work_date BETWEEN ? AND ?");
$cnt_schedule->execute([$date_from, $date_to]);
$n_schedule = (int)$cnt_schedule->fetchColumn();

// 预览：请假条数
$cnt_leave = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE start_date <= ? AND end_date >= ?");
$cnt_leave->execute([$date_to, $date_from]);
$n_leave = (int)$cnt_leave->fetchColumn();
?>

<div class="page-title">
    <h2>报表导出</h2>
    <p>按日期范围导出排班记录或请假记录（CSV，支持 Excel 直接打开）。</p>
</div>

<!-- 日期筛选 -->
<section class="panel">
    <h2>选择日期范围</h2>
    <form method="GET" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label>开始日期 <span style="color:red">*</span></label>
            <input type="date" name="date_from" value="<?= safe($date_from) ?>" required>
        </div>
        <div>
            <label>结束日期 <span style="color:red">*</span></label>
            <input type="date" name="date_to" value="<?= safe($date_to) ?>" required>
        </div>
        <div>
            <button class="btn" type="submit">更新预览</button>
        </div>
    </form>
</section>

<!-- 导出卡片 -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:8px;">

    <!-- 排班记录 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:12px;">
        <div>
            <h2 style="margin:0 0 4px;">📅 排班记录</h2>
            <p style="margin:0;color:#666;font-size:14px;">
                <?= safe($date_from) ?> 至 <?= safe($date_to) ?>
            </p>
        </div>
        <p style="margin:0;font-size:28px;font-weight:bold;color:#5c7cfa;">
            <?= $n_schedule ?>
            <span style="font-size:14px;font-weight:normal;color:#888;">条记录</span>
        </p>
        <p style="margin:0;font-size:13px;color:#888;">
            包含字段：日期、员工姓名、部门、职位、班次、开始/结束时间、备注、创建人
        </p>
        <?php if ($n_schedule > 0): ?>
            <a href="?date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&type=schedule"
               class="btn" style="text-align:center;text-decoration:none;display:block;">
                ⬇ 导出排班 CSV
            </a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.5;cursor:not-allowed;">
                该区间无排班记录
            </button>
        <?php endif; ?>
    </section>

    <!-- 请假记录 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:12px;">
        <div>
            <h2 style="margin:0 0 4px;">📄 请假记录</h2>
            <p style="margin:0;color:#666;font-size:14px;">
                <?= safe($date_from) ?> 至 <?= safe($date_to) ?>
            </p>
        </div>
        <p style="margin:0;font-size:28px;font-weight:bold;color:#5c7cfa;">
            <?= $n_leave ?>
            <span style="font-size:14px;font-weight:normal;color:#888;">条记录</span>
        </p>
        <p style="margin:0;font-size:13px;color:#888;">
            包含字段：开始/结束日期、员工姓名、部门、假期类型、原因、审批状态、审批备注
        </p>
        <?php if ($n_leave > 0): ?>
            <a href="?date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&type=leave"
               class="btn" style="text-align:center;text-decoration:none;display:block;">
                ⬇ 导出请假 CSV
            </a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.5;cursor:not-allowed;">
                该区间无请假记录
            </button>
        <?php endif; ?>
    </section>

</div>

<div class="panel" style="margin-top:20px;background:#fffbe6;border:1px solid #ffe58f;">
    <p style="margin:0;font-size:13px;color:#7d6608;">
        💡 <strong>提示：</strong>
        导出的 CSV 文件已加入 UTF-8 BOM，可直接用 Excel 打开中文不乱码。
        文件名格式：<code>类型_开始日期_至_结束日期.csv</code>
    </p>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
