<?php
require_once __DIR__ . '/header.php';

$me         = current_user();
$can_manage = has_role(['Admin', 'Manager']);
$emp_id     = $me['employee_id'] ?? null;

$message = '';
$error   = '';

// ── 辅助：冲突检测 ──────────────────────────────────────────────
// 区间重叠：existing_start < new_end AND existing_end > new_start
function check_booking_conflict($pdo, $venue_id, $booking_date, $start_time, $end_time, $exclude_id = 0) {
    $stmt = $pdo->prepare("
        SELECT id FROM venue_bookings
        WHERE venue_id     = ?
          AND booking_date = ?
          AND id          != ?
          AND status      != '已取消'
          AND start_time   < ?
          AND end_time     > ?
    ");
    $stmt->execute([$venue_id, $booking_date, $exclude_id, $end_time, $start_time]);
    return $stmt->fetch();
}

// ── POST 处理 ────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 新增预约（所有角色）
    if ($action === 'add') {
        verify_csrf();
        $venue_id    = intval($_POST['venue_id']    ?? 0);
        $title       = trim($_POST['title']         ?? '');
        $booking_date = trim($_POST['booking_date'] ?? '');
        $start_time  = trim($_POST['start_time']    ?? '');
        $end_time    = trim($_POST['end_time']      ?? '');
        $purpose     = trim($_POST['purpose']       ?? '');

        if ($venue_id <= 0 || $title === '' || $booking_date === '' || $start_time === '' || $end_time === '') {
            $error = '场地、标题、日期、开始时间和结束时间均为必填项。';
        } elseif ($start_time >= $end_time) {
            $error = '结束时间必须晚于开始时间。';
        } elseif (!$emp_id) {
            $error = '您的账号未关联员工档案，无法提交预约。';
        } else {
            // 冲突检测
            if (check_booking_conflict($pdo, $venue_id, $booking_date, $start_time, $end_time)) {
                $error = '该场地在所选时段已有预约，请选择其他时间。';
            } else {
                $pdo->prepare("
                    INSERT INTO venue_bookings
                        (venue_id, employee_id, title, booking_date, start_time, end_time, purpose, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, '待确认')
                ")->execute([$venue_id, $emp_id, $title, $booking_date, $start_time, $end_time, $purpose]);

                // 获取场地名称用于日志
                $vn = $pdo->prepare("SELECT name FROM venues WHERE id = ?");
                $vn->execute([$venue_id]);
                $vname = $vn->fetchColumn() ?: "ID {$venue_id}";
                write_audit_log('场地预约', '新增预约', "预约场地「{$vname}」{$booking_date} {$start_time}~{$end_time}：{$title}");
                $message = '预约已提交。';
            }
        }
    }

    // 编辑预约
    if ($action === 'edit') {
        verify_csrf();
        $bid         = intval($_POST['id']          ?? 0);
        $venue_id    = intval($_POST['venue_id']    ?? 0);
        $title       = trim($_POST['title']         ?? '');
        $booking_date = trim($_POST['booking_date'] ?? '');
        $start_time  = trim($_POST['start_time']    ?? '');
        $end_time    = trim($_POST['end_time']      ?? '');
        $purpose     = trim($_POST['purpose']       ?? '');
        $status_val  = $_POST['status']             ?? '待确认';

        $allowed_status = ['待确认', '已确认', '已取消'];
        if (!in_array($status_val, $allowed_status, true)) $status_val = '待确认';

        // 权限：Admin/Manager 可编辑全部；Employee 只能编辑自己的且状态为待确认
        $chk = $pdo->prepare("SELECT * FROM venue_bookings WHERE id = ?");
        $chk->execute([$bid]);
        $booking = $chk->fetch();

        if (!$booking) {
            $error = '预约记录不存在。';
        } elseif (!$can_manage && (intval($booking['employee_id']) !== intval($emp_id))) {
            $error = '无权编辑此预约。';
        } elseif (!$can_manage && $booking['status'] !== '待确认') {
            $error = '只能编辑「待确认」状态的预约。';
        } elseif ($bid <= 0 || $venue_id <= 0 || $title === '' || $booking_date === '' || $start_time === '' || $end_time === '') {
            $error = '所有必填项不能为空。';
        } elseif ($start_time >= $end_time) {
            $error = '结束时间必须晚于开始时间。';
        } else {
            if (check_booking_conflict($pdo, $venue_id, $booking_date, $start_time, $end_time, $bid)) {
                $error = '该场地在所选时段已有其他预约，请调整时间。';
            } else {
                $pdo->prepare("
                    UPDATE venue_bookings
                    SET venue_id = ?, title = ?, booking_date = ?,
                        start_time = ?, end_time = ?, purpose = ?, status = ?
                    WHERE id = ?
                ")->execute([$venue_id, $title, $booking_date, $start_time, $end_time, $purpose, $status_val, $bid]);

                $vn = $pdo->prepare("SELECT name FROM venues WHERE id = ?");
                $vn->execute([$venue_id]);
                $vname = $vn->fetchColumn() ?: "ID {$venue_id}";
                write_audit_log('场地预约', '编辑预约', "编辑预约 ID {$bid}：「{$vname}」{$booking_date} {$start_time}~{$end_time}");
                $message = '预约已更新。';
            }
        }
    }

    // 删除预约
    if ($action === 'delete') {
        verify_csrf();
        $bid = intval($_POST['id'] ?? 0);

        $chk = $pdo->prepare("SELECT * FROM venue_bookings WHERE id = ?");
        $chk->execute([$bid]);
        $booking = $chk->fetch();

        if (!$booking) {
            $error = '预约记录不存在。';
        } elseif (!$can_manage && (intval($booking['employee_id']) !== intval($emp_id))) {
            $error = '无权删除此预约。';
        } elseif (!$can_manage && $booking['status'] !== '待确认') {
            $error = '只能删除「待确认」状态的预约。';
        } else {
            $pdo->prepare("DELETE FROM venue_bookings WHERE id = ?")->execute([$bid]);
            write_audit_log('场地预约', '删除预约', "删除预约 ID {$bid}：{$booking['title']} {$booking['booking_date']}");
            $message = '预约已删除。';
        }
    }
}

// ── 查询列表 ─────────────────────────────────────────────────────
$per_page   = 25;
$page       = max(1, intval($_GET['page']        ?? 1));
$q_venue    = trim($_GET['venue']               ?? '');
$q_status   = $_GET['status']                   ?? '';
$q_date     = trim($_GET['date']                ?? '');

$where  = [];
$params = [];

// Employee 只看自己
if (!$can_manage) {
    $where[]  = 'b.employee_id = ?';
    $params[] = $emp_id ?: 0;
}

if ($q_venue !== '') {
    $like     = '%' . $q_venue . '%';
    $where[]  = '(v.name LIKE ? OR b.title LIKE ?)';
    $params   = array_merge($params, [$like, $like]);
}
if (in_array($q_status, ['待确认', '已确认', '已取消'], true)) {
    $where[]  = 'b.status = ?';
    $params[] = $q_status;
}
if ($q_date !== '') {
    $where[]  = 'b.booking_date = ?';
    $params[] = $q_date;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM venue_bookings b
    JOIN venues v ON b.venue_id = v.id
    LEFT JOIN employees e ON b.employee_id = e.id
    $where_sql
");
$total_stmt->execute($params);
$total_rows  = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$list_stmt = $pdo->prepare("
    SELECT b.*, v.name AS venue_name, v.location, e.name AS employee_name
    FROM venue_bookings b
    JOIN venues v ON b.venue_id = v.id
    LEFT JOIN employees e ON b.employee_id = e.id
    $where_sql
    ORDER BY b.booking_date DESC, b.start_time DESC
    LIMIT ? OFFSET ?
");
$list_stmt->execute(array_merge($params, [$per_page, $offset]));
$bookings = $list_stmt->fetchAll();

// 场地下拉（仅可用/占用的场地可预约）
$available_venues = $pdo->query("
    SELECT id, name, venue_code, category, location
    FROM venues
    WHERE status IN ('可用','占用')
    ORDER BY name ASC
")->fetchAll();

// 编辑模式
$editBooking = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $es  = $pdo->prepare("SELECT * FROM venue_bookings WHERE id = ?");
    $es->execute([$eid]);
    $row = $es->fetch() ?: null;
    // 权限校验
    if ($row && ($can_manage || intval($row['employee_id']) === intval($emp_id))) {
        $editBooking = $row;
    }
}

$qs_base = http_build_query(['venue' => $q_venue, 'status' => $q_status, 'date' => $q_date]);

$status_colors = [
    '待确认' => '#fd7e14',
    '已确认' => '#28a745',
    '已取消' => '#6c757d',
];
?>

<div class="page-title">
    <h2>场地预约</h2>
    <p>提交场地使用申请，系统自动检测时间冲突。</p>
</div>

<?php if ($message): ?><div class="success"><?= safe($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<!-- 新增 / 编辑表单 -->
<section class="panel">
    <h2><?= $editBooking ? '编辑预约' : '新增预约' ?></h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editBooking ? 'edit' : 'add' ?>">
        <?php if ($editBooking): ?>
            <input type="hidden" name="id" value="<?= intval($editBooking['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>预约标题 <span style="color:red">*</span></label>
                <input type="text" name="title" required
                       value="<?= safe($editBooking['title'] ?? '') ?>"
                       placeholder="例如：产品直播 / 团队会议">
            </div>
            <div>
                <label>选择场地 <span style="color:red">*</span></label>
                <select name="venue_id" required>
                    <option value="">— 请选择场地 —</option>
                    <?php foreach ($available_venues as $v): ?>
                        <option value="<?= intval($v['id']) ?>"
                            <?= (($editBooking['venue_id'] ?? 0) == $v['id']) ? 'selected' : '' ?>>
                            <?= safe($v['name']) ?>
                            <?= $v['venue_code'] ? '（' . safe($v['venue_code']) . '）' : '' ?>
                            <?= $v['location'] ? ' · ' . safe($v['location']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>预约日期 <span style="color:red">*</span></label>
                <input type="date" name="booking_date" required
                       value="<?= safe($editBooking['booking_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div>
                <label>开始时间 <span style="color:red">*</span></label>
                <input type="time" name="start_time" required
                       value="<?= safe($editBooking['start_time'] ?? '') ?>">
            </div>
            <div>
                <label>结束时间 <span style="color:red">*</span></label>
                <input type="time" name="end_time" required
                       value="<?= safe($editBooking['end_time'] ?? '') ?>">
            </div>
            <?php if ($can_manage && $editBooking): ?>
            <div>
                <label>状态</label>
                <select name="status">
                    <?php foreach (['待确认', '已确认', '已取消'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editBooking['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= $s ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div style="grid-column:1/-1;">
                <label>用途说明</label>
                <input type="text" name="purpose"
                       value="<?= safe($editBooking['purpose'] ?? '') ?>"
                       placeholder="例如：618 直播专场、季度复盘会">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:14px;">
            <button class="btn" type="submit"><?= $editBooking ? '保存修改' : '提交预约' ?></button>
            <?php if ($editBooking): ?>
                <a class="btn secondary" href="venue_bookings.php?<?= $qs_base ?>">取消编辑</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- 筛选 -->
<section class="panel">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:150px;">
            <label>搜索</label>
            <input type="text" name="venue" value="<?= safe($q_venue) ?>"
                   placeholder="场地名称或预约标题…" style="width:100%;">
        </div>
        <div>
            <label>状态</label>
            <select name="status">
                <option value="">全部状态</option>
                <?php foreach (['待确认', '已确认', '已取消'] as $s): ?>
                    <option value="<?= $s ?>" <?= $q_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>日期</label>
            <input type="date" name="date" value="<?= safe($q_date) ?>">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">搜索</button>
            <?php if ($q_venue || $q_status || $q_date): ?>
                <a class="btn" href="venue_bookings.php"
                   style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<p style="margin-bottom:12px;color:#666;">
    共 <strong><?= $total_rows ?></strong> 条记录 · 第 <?= $page ?> / <?= $total_pages ?> 页
    <?= !$can_manage ? '（仅显示本人预约）' : '' ?>
</p>

<!-- 预约列表 -->
<section class="panel" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>预约标题</th>
                    <th>场地</th>
                    <th>日期</th>
                    <th>时间段</th>
                    <th>用途</th>
                    <?php if ($can_manage): ?><th>预约人</th><?php endif; ?>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($bookings): ?>
                <?php foreach ($bookings as $b):
                    $is_own = intval($b['employee_id']) === intval($emp_id);
                    $can_edit = $can_manage || ($is_own && $b['status'] === '待确认');
                ?>
                <tr>
                    <td style="color:#aaa;font-size:12px;"><?= intval($b['id']) ?></td>
                    <td><strong><?= safe($b['title']) ?></strong></td>
                    <td>
                        <?= safe($b['venue_name']) ?>
                        <?php if ($b['location']): ?>
                            <br><small style="color:#888;"><?= safe($b['location']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= safe($b['booking_date']) ?></td>
                    <td style="white-space:nowrap;">
                        <?= safe(substr($b['start_time'], 0, 5)) ?> — <?= safe(substr($b['end_time'], 0, 5)) ?>
                    </td>
                    <td style="font-size:13px;color:#555;"><?= safe($b['purpose'] ?: '—') ?></td>
                    <?php if ($can_manage): ?>
                    <td><?= safe($b['employee_name'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;
                                     font-size:12px;font-weight:bold;color:#fff;
                                     background:<?= $status_colors[$b['status']] ?? '#888' ?>;">
                            <?= safe($b['status']) ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if ($can_edit): ?>
                            <a href="venue_bookings.php?edit=<?= intval($b['id']) ?>&<?= $qs_base ?>">编辑</a>
                            |
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('确定要删除「<?= safe($b['title']) ?>」吗？')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= intval($b['id']) ?>">
                                <button type="submit" class="btn-link">删除</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $can_manage ? 9 : 8 ?>"
                        style="text-align:center;color:#888;padding:32px;">
                        <?= ($q_venue||$q_status||$q_date) ? '未找到符合条件的预约。' : '暂无预约记录。' ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
    <div style="padding:16px 20px;display:flex;gap:6px;flex-wrap:wrap;">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?<?= http_build_query(['venue'=>$q_venue,'status'=>$q_status,'date'=>$q_date,'page'=>$p]) ?>"
               style="padding:6px 12px;border-radius:6px;border:1px solid #ddd;text-decoration:none;
                      <?= $p===$page?'background:#5c7cfa;color:#fff;border-color:#5c7cfa;':'color:#333;' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
