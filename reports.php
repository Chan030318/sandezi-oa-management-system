<?php
ob_start();
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

// ── 公共参数 ─────────────────────────────────────────────────────
$default_start = date('Y-m-01');
$default_end   = date('Y-m-d');

$date_from     = $_GET['date_from']     ?? $default_start;
$date_to       = $_GET['date_to']       ?? $default_end;
$type          = $_GET['type']          ?? '';
$dev_status    = $_GET['dev_status']    ?? '';   // 设备状态筛选

// 日期校验
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = $default_start;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = $default_end;
if ($date_from > $date_to) [$date_from, $date_to] = [$date_to, $date_from];

$allowed_dev_status = ['空闲', '使用中', '维修中', '报废'];
if (!in_array($dev_status, $allowed_dev_status, true)) $dev_status = '';

$venue_status   = $_GET['venue_status']   ?? '';
$booking_status = $_GET['booking_status'] ?? '';

$allowed_venue_status   = ['可用', '占用', '维修中', '停用'];
$allowed_booking_status = ['待确认', '已确认', '已取消'];
if (!in_array($venue_status,   $allowed_venue_status,   true)) $venue_status   = '';
if (!in_array($booking_status, $allowed_booking_status, true)) $booking_status = '';

// ── CSV 输出辅助 ──────────────────────────────────────────────────
function output_csv(string $filename, array $headers, array $rows): void
{
    ob_end_clean(); // discard buffered HTML before sending CSV headers
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM
    fputcsv($out, $headers);
    foreach ($rows as $row) { fputcsv($out, $row); }
    fclose($out);
    exit;
}

// ════════════════════════════════════════════════════════════════
// 导出：排班记录
// ════════════════════════════════════════════════════════════════
if ($type === 'schedule') {
    $stmt = $pdo->prepare("
        SELECT s.work_date, e.name AS employee_name,
               d.name AS department_name, e.position,
               sh.name AS shift_name, sh.start_time, sh.end_time,
               s.remark, u.name AS created_by_name, s.created_at
        FROM schedules s
        JOIN employees   e  ON s.employee_id = e.id
        LEFT JOIN departments d  ON e.department_id = d.id
        JOIN shifts      sh ON s.shift_id    = sh.id
        LEFT JOIN users  u  ON s.created_by  = u.id
        WHERE s.work_date BETWEEN ? AND ?
        ORDER BY s.work_date ASC, e.name ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $csv_rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $csv_rows[] = [
            $r['work_date'], $r['employee_name'],
            $r['department_name'] ?? '', $r['position'],
            $r['shift_name'], $r['start_time'], $r['end_time'],
            $r['remark'] ?? '', $r['created_by_name'] ?? '', $r['created_at'],
        ];
    }
    output_csv(
        '排班记录_' . $date_from . '_至_' . $date_to . '.csv',
        ['日期','员工姓名','部门','职位','班次','开始时间','结束时间','备注','创建人','创建时间'],
        $csv_rows
    );
}

// ════════════════════════════════════════════════════════════════
// 导出：请假记录
// ════════════════════════════════════════════════════════════════
if ($type === 'leave') {
    $stmt = $pdo->prepare("
        SELECT l.start_date, l.end_date, e.name AS employee_name,
               d.name AS department_name, e.position,
               l.leave_type, l.reason, l.status, l.approve_remark, l.created_at
        FROM leaves l
        JOIN employees   e ON l.employee_id    = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE l.start_date <= ? AND l.end_date >= ?
        ORDER BY l.start_date ASC, e.name ASC
    ");
    $stmt->execute([$date_to, $date_from]);
    $status_map = ['Pending' => '待审核', 'Approved' => '已批准', 'Rejected' => '已拒绝'];
    $csv_rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $csv_rows[] = [
            $r['start_date'], $r['end_date'], $r['employee_name'],
            $r['department_name'] ?? '', $r['position'], $r['leave_type'],
            $r['reason'] ?? '', $status_map[$r['status']] ?? $r['status'],
            $r['approve_remark'] ?? '', $r['created_at'],
        ];
    }
    output_csv(
        '请假记录_' . $date_from . '_至_' . $date_to . '.csv',
        ['开始日期','结束日期','员工姓名','部门','职位','假期类型','请假原因','审批状态','审批备注','申请时间'],
        $csv_rows
    );
}

// ════════════════════════════════════════════════════════════════
// 导出：设备台账
// ════════════════════════════════════════════════════════════════
if ($type === 'devices') {
    $where  = ['d.created_at <= ?'];
    $params = [$date_to . ' 23:59:59'];

    if ($dev_status !== '') {
        $where[]  = 'd.status = ?';
        $params[] = $dev_status;
    }
    // 台账的 date_from 过滤：created_at >= date_from 00:00:00
    $where[]  = 'd.created_at >= ?';
    $params[] = $date_from . ' 00:00:00';

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT d.device_code, d.asset_code, d.name, d.category,
               d.brand, d.model, d.serial_number,
               dept.name AS department_name,
               d.manager, d.status, d.created_at
        FROM devices d
        LEFT JOIN departments dept ON d.department_id = dept.id
        $where_sql
        ORDER BY d.id ASC
    ");
    $stmt->execute($params);
    $csv_rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $csv_rows[] = [
            $r['device_code']  ?? '',
            $r['asset_code']   ?? '',
            $r['name'],
            $r['category'],
            $r['brand'],
            $r['model'],
            $r['serial_number'] ?? '',
            $r['department_name'] ?? '',
            $r['manager'],
            $r['status'],
            $r['created_at'],
        ];
    }
    $suffix = $dev_status ? ('_' . $dev_status) : '';
    output_csv(
        '设备台账' . $suffix . '_' . $date_from . '_至_' . $date_to . '.csv',
        ['设备编号','资产编号','设备名称','类别','品牌','型号','序列号','所属部门','负责人','状态','录入时间'],
        $csv_rows
    );
}

// ════════════════════════════════════════════════════════════════
// 导出：设备借用记录
// ════════════════════════════════════════════════════════════════
if ($type === 'device_borrow') {
    $stmt = $pdo->prepare("
        SELECT b.created_at AS apply_time,
               b.borrow_start, b.borrow_end,
               d.name AS device_name, d.device_code,
               d.category, d.brand, d.model,
               e.name AS employee_name,
               dept.name AS department_name,
               b.purpose, b.status AS borrow_status,
               u.name AS approver_name,
               b.approved_at,
               b.returned_at, b.return_note
        FROM device_borrows b
        JOIN devices   d    ON b.device_id   = d.id
        LEFT JOIN employees e    ON b.employee_id = e.id
        LEFT JOIN departments dept ON e.department_id = dept.id
        LEFT JOIN users  u    ON b.approved_by = u.id
        WHERE b.borrow_start <= ? AND b.borrow_end >= ?
        ORDER BY b.borrow_start ASC, b.id ASC
    ");
    $stmt->execute([$date_to, $date_from]);
    $csv_rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $csv_rows[] = [
            $r['apply_time'],
            $r['borrow_start'],
            $r['borrow_end'],
            $r['device_name'],
            $r['device_code']   ?? '',
            $r['category'],
            $r['brand'] . ' ' . $r['model'],
            $r['employee_name'] ?? '',
            $r['department_name'] ?? '',
            $r['purpose'],
            $r['borrow_status'],
            $r['approver_name'] ?? '',
            $r['approved_at']   ?? '',
            $r['returned_at']   ?? '',
            $r['return_note']   ?? '',
        ];
    }
    output_csv(
        '设备借用记录_' . $date_from . '_至_' . $date_to . '.csv',
        ['申请时间','借用开始','预计归还','设备名称','设备编号','类别','品牌型号',
         '申请人','部门','用途','状态','审批人','审批时间','实际归还时间','归还备注'],
        $csv_rows
    );
}

// ════════════════════════════════════════════════════════════════
// 导出：设备维修记录
// ════════════════════════════════════════════════════════════════
if ($type === 'device_maintenance') {
    $where  = ['m.created_at BETWEEN ? AND ?'];
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

    if ($dev_status !== '') {
        $where[]  = 'm.status = ?';
        $params[] = $dev_status;
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT m.created_at, d.name AS device_name, d.device_code,
               d.category, er.name AS reporter_name,
               dept.name AS department_name,
               m.issue_title, m.issue_description,
               m.status AS maint_status,
               m.cost, eu.name AS handler_name,
               m.handled_at, m.note
        FROM device_maintenance m
        JOIN devices    d    ON m.device_id  = d.id
        LEFT JOIN employees er   ON m.report_by  = er.id
        LEFT JOIN departments dept ON er.department_id = dept.id
        LEFT JOIN users  eu   ON m.handled_by = eu.id
        $where_sql
        ORDER BY m.created_at ASC
    ");
    $stmt->execute($params);
    $csv_rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $csv_rows[] = [
            $r['created_at'],
            $r['device_name'],
            $r['device_code']   ?? '',
            $r['category'],
            $r['reporter_name'] ?? '',
            $r['department_name'] ?? '',
            $r['issue_title'],
            $r['issue_description'] ?? '',
            $r['maint_status'],
            $r['cost'] !== null ? 'RM ' . number_format((float)$r['cost'], 2) : '',
            $r['handler_name']  ?? '',
            $r['handled_at']    ?? '',
            $r['note']          ?? '',
        ];
    }
    output_csv(
        '设备维修记录_' . $date_from . '_至_' . $date_to . '.csv',
        ['提交时间','设备名称','设备编号','类别','报修人','部门','问题标题','问题描述',
         '维修状态','维修费用','处理人','处理时间','备注'],
        $csv_rows
    );
}

// ════════════════════════════════════════════════════════════════
// 导出：场地列表
// ════════════════════════════════════════════════════════════════
if ($type === 'venues') {
    $where  = ['v.created_at BETWEEN ? AND ?'];
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

    if ($venue_status !== '') {
        $where[]  = 'v.status = ?';
        $params[] = $venue_status;
    }

    $stmt = $pdo->prepare("
        SELECT v.venue_code, v.name, v.category, v.location,
               v.capacity, v.contact_person, v.contact_phone,
               v.status, v.created_at
        FROM venues v
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.id ASC
    ");
    $stmt->execute($params);
    $csv_rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $csv_rows[] = [
            $r['venue_code']     ?? '',
            $r['name'],
            $r['category'],
            $r['location']       ?? '',
            $r['capacity']       ?? '',
            $r['contact_person'] ?? '',
            $r['contact_phone']  ?? '',
            $r['status'],
            $r['created_at'],
        ];
    }
    $suffix = $venue_status ? ('_' . $venue_status) : '';
    output_csv(
        '场地列表' . $suffix . '_' . $date_from . '_至_' . $date_to . '.csv',
        ['场地编号', '场地名称', '类型', '位置', '容量', '联系人', '电话', '状态', '创建时间'],
        $csv_rows
    );
}

// ════════════════════════════════════════════════════════════════
// 导出：场地预约记录
// ════════════════════════════════════════════════════════════════
if ($type === 'venue_bookings') {
    $where  = ['b.booking_date BETWEEN ? AND ?'];
    $params = [$date_from, $date_to];

    if ($booking_status !== '') {
        $where[]  = 'b.status = ?';
        $params[] = $booking_status;
    }

    $stmt = $pdo->prepare("
        SELECT b.title, v.name AS venue_name, v.category AS venue_category,
               e.name AS employee_name,
               d.name AS department_name,
               b.booking_date, b.start_time, b.end_time,
               b.purpose, b.status, b.created_at
        FROM venue_bookings b
        JOIN venues       v    ON b.venue_id    = v.id
        LEFT JOIN employees    e    ON b.employee_id = e.id
        LEFT JOIN departments  d    ON e.department_id = d.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.booking_date ASC, b.start_time ASC
    ");
    $stmt->execute($params);
    $csv_rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $csv_rows[] = [
            $r['title'],
            $r['venue_name'],
            $r['venue_category'],
            $r['employee_name']   ?? '',
            $r['department_name'] ?? '',
            $r['booking_date'],
            substr($r['start_time'], 0, 5),
            substr($r['end_time'],   0, 5),
            $r['purpose']  ?? '',
            $r['status'],
            $r['created_at'],
        ];
    }
    $suffix = $booking_status ? ('_' . $booking_status) : '';
    output_csv(
        '场地预约记录' . $suffix . '_' . $date_from . '_至_' . $date_to . '.csv',
        ['预约标题', '场地名称', '场地类型', '预约人', '部门',
         '日期', '开始时间', '结束时间', '用途', '状态', '创建时间'],
        $csv_rows
    );
}

// ════════════════════════════════════════════════════════════════
// 预览页面
// ════════════════════════════════════════════════════════════════

// HR 报表预览数
$cnt_schedule = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE work_date BETWEEN ? AND ?");
$cnt_schedule->execute([$date_from, $date_to]);
$n_schedule = (int)$cnt_schedule->fetchColumn();

$cnt_leave = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE start_date <= ? AND end_date >= ?");
$cnt_leave->execute([$date_to, $date_from]);
$n_leave = (int)$cnt_leave->fetchColumn();

// 设备报表预览数
$dev_where  = ['d.created_at BETWEEN ? AND ?'];
$dev_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
if ($dev_status !== '') { $dev_where[] = 'd.status = ?'; $dev_params[] = $dev_status; }
$cnt_devices_stmt = $pdo->prepare("SELECT COUNT(*) FROM devices d WHERE " . implode(' AND ', $dev_where));
$cnt_devices_stmt->execute($dev_params);
$n_devices = (int)$cnt_devices_stmt->fetchColumn();

$cnt_borrow = $pdo->prepare("SELECT COUNT(*) FROM device_borrows WHERE borrow_start <= ? AND borrow_end >= ?");
$cnt_borrow->execute([$date_to, $date_from]);
$n_borrow = (int)$cnt_borrow->fetchColumn();

$maint_where  = ['created_at BETWEEN ? AND ?'];
$maint_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
if ($dev_status !== '') { $maint_where[] = 'status = ?'; $maint_params[] = $dev_status; }
$cnt_maint_stmt = $pdo->prepare("SELECT COUNT(*) FROM device_maintenance WHERE " . implode(' AND ', $maint_where));
$cnt_maint_stmt->execute($maint_params);
$n_maint = (int)$cnt_maint_stmt->fetchColumn();

// 场地报表预览数
$venue_where  = ['v.created_at BETWEEN ? AND ?'];
$venue_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
if ($venue_status !== '') { $venue_where[] = 'v.status = ?'; $venue_params[] = $venue_status; }
$cnt_venues_stmt = $pdo->prepare("SELECT COUNT(*) FROM venues v WHERE " . implode(' AND ', $venue_where));
$cnt_venues_stmt->execute($venue_params);
$n_venues = (int)$cnt_venues_stmt->fetchColumn();

$bk_where  = ['b.booking_date BETWEEN ? AND ?'];
$bk_params = [$date_from, $date_to];
if ($booking_status !== '') { $bk_where[] = 'b.status = ?'; $bk_params[] = $booking_status; }
$cnt_bk_stmt = $pdo->prepare("SELECT COUNT(*) FROM venue_bookings b WHERE " . implode(' AND ', $bk_where));
$cnt_bk_stmt->execute($bk_params);
$n_venue_bookings = (int)$cnt_bk_stmt->fetchColumn();

// 辅助：生成带参数的导出链接
function export_url(string $t, string $df, string $dt, string $ds): string {
    return '?' . http_build_query(['type' => $t, 'date_from' => $df, 'date_to' => $dt, 'dev_status' => $ds]);
}
function venue_export_url(string $t, string $df, string $dt, string $vs, string $bs): string {
    return '?' . http_build_query(['type' => $t, 'date_from' => $df, 'date_to' => $dt, 'venue_status' => $vs, 'booking_status' => $bs]);
}
?>

<div class="page-title">
    <h2>报表导出</h2>
    <p>按日期范围导出各类记录（CSV，支持 Excel 直接打开中文不乱码）。</p>
</div>

<!-- 筛选条件 -->
<section class="panel">
    <h2>筛选条件</h2>
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
            <label>设备状态筛选</label>
            <select name="dev_status">
                <option value="">全部状态</option>
                <?php foreach (['空闲','使用中','维修中','报废'] as $s): ?>
                    <option value="<?= $s ?>" <?= $dev_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>场地状态筛选</label>
            <select name="venue_status">
                <option value="">全部状态</option>
                <?php foreach (['可用','占用','维修中','停用'] as $s): ?>
                    <option value="<?= $s ?>" <?= $venue_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>预约状态筛选</label>
            <select name="booking_status">
                <option value="">全部状态</option>
                <?php foreach (['待确认','已确认','已取消'] as $s): ?>
                    <option value="<?= $s ?>" <?= $booking_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button class="btn" type="submit">更新预览</button>
        </div>
    </form>
    <p style="margin:10px 0 0;font-size:13px;color:#888;">
        ⚠ 设备状态筛选仅影响「设备台账」和「设备维修」导出；场地/预约状态筛选仅影响「场地报表」导出；HR 报表不受影响。
    </p>
</section>

<!-- ── HR 报表 ─────────────────────────────────── -->
<h3 style="margin:24px 0 12px;color:#555;">📋 HR 报表</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">

    <!-- 排班记录 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:10px;">
        <div>
            <h2 style="margin:0 0 3px;">📅 排班记录</h2>
            <p style="margin:0;color:#666;font-size:13px;"><?= safe($date_from) ?> 至 <?= safe($date_to) ?></p>
        </div>
        <p style="margin:0;font-size:26px;font-weight:bold;color:#5c7cfa;">
            <?= $n_schedule ?> <span style="font-size:13px;font-weight:normal;color:#888;">条</span>
        </p>
        <p style="margin:0;font-size:12px;color:#aaa;">日期·员工·部门·职位·班次·时间</p>
        <?php if ($n_schedule > 0): ?>
            <a href="<?= export_url('schedule', $date_from, $date_to, '') ?>"
               class="btn" style="text-align:center;text-decoration:none;">⬇ 导出 CSV</a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.45;cursor:not-allowed;">无记录</button>
        <?php endif; ?>
    </section>

    <!-- 请假记录 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:10px;">
        <div>
            <h2 style="margin:0 0 3px;">📄 请假记录</h2>
            <p style="margin:0;color:#666;font-size:13px;"><?= safe($date_from) ?> 至 <?= safe($date_to) ?></p>
        </div>
        <p style="margin:0;font-size:26px;font-weight:bold;color:#5c7cfa;">
            <?= $n_leave ?> <span style="font-size:13px;font-weight:normal;color:#888;">条</span>
        </p>
        <p style="margin:0;font-size:12px;color:#aaa;">日期·员工·类型·原因·审批状态</p>
        <?php if ($n_leave > 0): ?>
            <a href="<?= export_url('leave', $date_from, $date_to, '') ?>"
               class="btn" style="text-align:center;text-decoration:none;">⬇ 导出 CSV</a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.45;cursor:not-allowed;">无记录</button>
        <?php endif; ?>
    </section>
</div>

<!-- ── 设备报表 ────────────────────────────────── -->
<h3 style="margin:28px 0 12px;color:#555;">📦 设备报表</h3>
<?php if ($dev_status): ?>
    <p style="margin:-4px 0 12px;font-size:13px;color:#5c7cfa;">
        当前筛选设备状态：<strong><?= safe($dev_status) ?></strong>
    </p>
<?php endif; ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">

    <!-- 设备台账 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:10px;">
        <div>
            <h2 style="margin:0 0 3px;">🗂 设备台账</h2>
            <p style="margin:0;color:#666;font-size:13px;">录入时间 <?= safe($date_from) ?> 至 <?= safe($date_to) ?></p>
        </div>
        <p style="margin:0;font-size:26px;font-weight:bold;color:#5c7cfa;">
            <?= $n_devices ?> <span style="font-size:13px;font-weight:normal;color:#888;">台</span>
        </p>
        <p style="margin:0;font-size:12px;color:#aaa;">编号·名称·类别·品牌·型号·序列号·部门·负责人·状态</p>
        <?php if ($n_devices > 0): ?>
            <a href="<?= export_url('devices', $date_from, $date_to, $dev_status) ?>"
               class="btn" style="text-align:center;text-decoration:none;">⬇ 导出 CSV</a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.45;cursor:not-allowed;">无记录</button>
        <?php endif; ?>
    </section>

    <!-- 设备借用记录 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:10px;">
        <div>
            <h2 style="margin:0 0 3px;">📤 设备借用记录</h2>
            <p style="margin:0;color:#666;font-size:13px;">借用日期含 <?= safe($date_from) ?> 至 <?= safe($date_to) ?></p>
        </div>
        <p style="margin:0;font-size:26px;font-weight:bold;color:#5c7cfa;">
            <?= $n_borrow ?> <span style="font-size:13px;font-weight:normal;color:#888;">条</span>
        </p>
        <p style="margin:0;font-size:12px;color:#aaa;">申请人·设备·用途·状态·审批·归还时间</p>
        <?php if ($n_borrow > 0): ?>
            <a href="<?= export_url('device_borrow', $date_from, $date_to, '') ?>"
               class="btn" style="text-align:center;text-decoration:none;">⬇ 导出 CSV</a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.45;cursor:not-allowed;">无记录</button>
        <?php endif; ?>
    </section>

    <!-- 设备维修记录 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:10px;">
        <div>
            <h2 style="margin:0 0 3px;">🛠 设备维修记录</h2>
            <p style="margin:0;color:#666;font-size:13px;">提交时间 <?= safe($date_from) ?> 至 <?= safe($date_to) ?></p>
        </div>
        <p style="margin:0;font-size:26px;font-weight:bold;color:#5c7cfa;">
            <?= $n_maint ?> <span style="font-size:13px;font-weight:normal;color:#888;">条</span>
        </p>
        <p style="margin:0;font-size:12px;color:#aaa;">设备·问题·状态·费用·处理人·备注</p>
        <?php if ($n_maint > 0): ?>
            <a href="<?= export_url('device_maintenance', $date_from, $date_to, $dev_status) ?>"
               class="btn" style="text-align:center;text-decoration:none;">⬇ 导出 CSV</a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.45;cursor:not-allowed;">无记录</button>
        <?php endif; ?>
    </section>
</div>

<!-- ── 场地报表 ────────────────────────────────── -->
<h3 style="margin:28px 0 12px;color:#555;">🏢 场地报表</h3>
<?php if ($venue_status || $booking_status): ?>
    <p style="margin:-4px 0 12px;font-size:13px;color:#5c7cfa;">
        当前筛选：
        <?php if ($venue_status): ?><strong>场地状态：<?= safe($venue_status) ?></strong> <?php endif; ?>
        <?php if ($booking_status): ?><strong>预约状态：<?= safe($booking_status) ?></strong><?php endif; ?>
    </p>
<?php endif; ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">

    <!-- 场地列表 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:10px;">
        <div>
            <h2 style="margin:0 0 3px;">🏢 场地列表</h2>
            <p style="margin:0;color:#666;font-size:13px;">录入时间 <?= safe($date_from) ?> 至 <?= safe($date_to) ?></p>
        </div>
        <p style="margin:0;font-size:26px;font-weight:bold;color:#5c7cfa;">
            <?= $n_venues ?> <span style="font-size:13px;font-weight:normal;color:#888;">条</span>
        </p>
        <p style="margin:0;font-size:12px;color:#aaa;">场地编号·名称·类型·位置·容量·联系人·状态</p>
        <?php if ($n_venues > 0): ?>
            <a href="<?= venue_export_url('venues', $date_from, $date_to, $venue_status, '') ?>"
               class="btn" style="text-align:center;text-decoration:none;">⬇ 导出 CSV</a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.45;cursor:not-allowed;">无记录</button>
        <?php endif; ?>
    </section>

    <!-- 场地预约记录 -->
    <section class="panel" style="display:flex;flex-direction:column;gap:10px;">
        <div>
            <h2 style="margin:0 0 3px;">📅 场地预约记录</h2>
            <p style="margin:0;color:#666;font-size:13px;">预约日期 <?= safe($date_from) ?> 至 <?= safe($date_to) ?></p>
        </div>
        <p style="margin:0;font-size:26px;font-weight:bold;color:#5c7cfa;">
            <?= $n_venue_bookings ?> <span style="font-size:13px;font-weight:normal;color:#888;">条</span>
        </p>
        <p style="margin:0;font-size:12px;color:#aaa;">标题·场地·预约人·部门·日期·时段·用途·状态</p>
        <?php if ($n_venue_bookings > 0): ?>
            <a href="<?= venue_export_url('venue_bookings', $date_from, $date_to, '', $booking_status) ?>"
               class="btn" style="text-align:center;text-decoration:none;">⬇ 导出 CSV</a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.45;cursor:not-allowed;">无记录</button>
        <?php endif; ?>
    </section>
</div>

<!-- 提示 -->
<div class="panel" style="margin-top:20px;background:#fffbe6;border:1px solid #ffe58f;">
    <p style="margin:0;font-size:13px;color:#7d6608;">
        💡 <strong>提示：</strong>
        所有 CSV 文件含 UTF-8 BOM，Excel 可直接打开中文不乱码。
        文件名格式：<code>类型_开始日期_至_结束日期.csv</code>
    </p>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
