<?php
require_once __DIR__ . '/header.php';

$message = '';
$error   = '';
$me      = current_user();
$can_manage = has_role(['Admin', 'Manager']);

// ── 提交报修 ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report') {
    verify_csrf();

    $device_id        = intval($_POST['device_id']        ?? 0);
    $issue_title      = trim($_POST['issue_title']        ?? '');
    $issue_description = trim($_POST['issue_description'] ?? '');

    if ($device_id <= 0) {
        $error = '请选择设备。';
    } elseif ($issue_title === '') {
        $error = '请填写问题标题。';
    } else {
        // 确认设备存在且未报废
        $dev = $pdo->prepare("SELECT id, name, status FROM devices WHERE id = ?");
        $dev->execute([$device_id]);
        $device = $dev->fetch();

        if (!$device) {
            $error = '设备不存在。';
        } elseif ($device['status'] === '报废') {
            $error = '该设备已报废，无法提交报修。';
        } else {
            $emp_id = $me['employee_id'] ?? null;
            $pdo->prepare("
                INSERT INTO device_maintenance
                    (device_id, report_by, issue_title, issue_description, status)
                VALUES (?, ?, ?, ?, '待处理')
            ")->execute([$device_id, $emp_id, $issue_title, $issue_description]);
            $message = '报修已提交，请等待管理员处理。';
        }
    }
}

// ── 更新维修状态（Admin / Manager）──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && $can_manage) {
    verify_csrf();

    $maint_id   = intval($_POST['maint_id']  ?? 0);
    $new_status = $_POST['new_status']       ?? '';
    $cost       = trim($_POST['cost']        ?? '');
    $note       = trim($_POST['note']        ?? '');

    $allowed = ['待处理', '维修中', '已完成', '已报废'];
    if ($maint_id <= 0 || !in_array($new_status, $allowed, true)) {
        $error = '参数无效。';
    } else {
        // 取得当前记录
        $chk = $pdo->prepare("SELECT * FROM device_maintenance WHERE id = ?");
        $chk->execute([$maint_id]);
        $maint = $chk->fetch();

        if (!$maint) {
            $error = '记录不存在。';
        } else {
            $cost_val = ($cost !== '' && is_numeric($cost)) ? floatval($cost) : null;

            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    UPDATE device_maintenance
                    SET status     = ?,
                        cost       = ?,
                        handled_by = ?,
                        handled_at = NOW(),
                        note       = ?
                    WHERE id = ?
                ")->execute([$new_status, $cost_val, $me['id'], $note, $maint_id]);

                // 同步设备状态
                $device_status_map = [
                    '维修中' => '维修中',
                    '已完成' => '空闲',
                    '已报废' => '报废',
                ];
                if (isset($device_status_map[$new_status])) {
                    $pdo->prepare("UPDATE devices SET status = ? WHERE id = ?")
                        ->execute([$device_status_map[$new_status], $maint['device_id']]);
                }

                $pdo->commit();
                $log_action = $new_status === '已报废' ? '标记报废' : '更新维修状态';
                write_audit_log('设备维修', $log_action, "维修记录 ID {$maint_id}（设备 ID {$maint['device_id']}）状态更新为：{$new_status}");
                $message = '维修状态已更新。';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = '操作失败，请重试。';
            }
        }
    }
}

// ── 可报修设备列表 ────────────────────────────────────────────
$reportable_devices = $pdo->query("
    SELECT id, name, device_code, category, status
    FROM devices
    WHERE status != '报废'
    ORDER BY name ASC
")->fetchAll();

// ── 报修记录列表 ─────────────────────────────────────────────
$emp_id = $me['employee_id'] ?? null;

$q_status = $_GET['status'] ?? '';
$q        = trim($_GET['q'] ?? '');
$per_page = 25;
$page     = max(1, intval($_GET['page'] ?? 1));

$where  = [];
$params = [];

if (!$can_manage && $emp_id) {
    $where[]  = 'm.report_by = ?';
    $params[] = $emp_id;
} elseif (!$can_manage && !$emp_id) {
    // 未绑定员工的账号看不到任何记录
    $where[] = '1 = 0';
}

if (in_array($q_status, ['待处理','维修中','已完成','已报废'], true)) {
    $where[]  = 'm.status = ?';
    $params[] = $q_status;
}
if ($q !== '') {
    $like     = '%' . $q . '%';
    $where[]  = '(d.name LIKE ? OR m.issue_title LIKE ? OR m.note LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like]);
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM device_maintenance m
    JOIN devices d ON m.device_id = d.id
    $where_sql
");
$total_stmt->execute($params);
$total_rows  = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$list_stmt = $pdo->prepare("
    SELECT m.*,
           d.name AS device_name, d.device_code,
           er.name AS reporter_name,
           eu.name AS handler_name
    FROM device_maintenance m
    JOIN devices    d  ON m.device_id   = d.id
    LEFT JOIN employees er ON m.report_by   = er.id
    LEFT JOIN users     eu ON m.handled_by  = eu.id
    $where_sql
    ORDER BY
        FIELD(m.status,'待处理','维修中','已完成','已报废'),
        m.id DESC
    LIMIT " . intval($offset) . ", " . intval($per_page));
$list_stmt->execute($params);
$records = $list_stmt->fetchAll();

$status_colors = [
    '待处理' => '#fd7e14',
    '维修中' => '#5c7cfa',
    '已完成' => '#28a745',
    '已报废' => '#6c757d',
];

$qs_base = http_build_query(['status' => $q_status, 'q' => $q]);
?>

<div class="page-title">
    <h2>设备维修管理</h2>
    <p>提交设备报修申请，管理员跟进维修进度与报废处理。</p>
</div>

<?php if ($message): ?><div class="success"><?= safe($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<!-- 报修表单 -->
<section class="panel">
    <h2>提交报修</h2>
    <?php if ($reportable_devices): ?>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="report">
        <div class="form-grid">
            <div style="grid-column:1/-1;">
                <label>选择设备 <span style="color:red">*</span></label>
                <select name="device_id" required>
                    <option value="">— 请选择设备 —</option>
                    <?php foreach ($reportable_devices as $dv): ?>
                        <option value="<?= intval($dv['id']) ?>">
                            <?= safe($dv['name']) ?>
                            <?= $dv['device_code'] ? '（' . safe($dv['device_code']) . '）' : '' ?>
                            · 当前状态：<?= safe($dv['status']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:1/-1;">
                <label>问题标题 <span style="color:red">*</span></label>
                <input type="text" name="issue_title" required
                       placeholder="例如：屏幕出现黑线、无法开机、镜头破损">
            </div>
            <div style="grid-column:1/-1;">
                <label>问题描述</label>
                <textarea name="issue_description" rows="4"
                          placeholder="详细描述故障情况、发生时间、影响范围等"></textarea>
            </div>
        </div>
        <button class="btn" type="submit" style="margin-top:10px;">提交报修</button>
    </form>
    <?php else: ?>
        <p style="color:#888;padding:12px 0;">目前没有可报修的设备。</p>
    <?php endif; ?>
</section>

<!-- 筛选 -->
<section class="panel">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label>状态</label>
            <select name="status">
                <option value="">全部状态</option>
                <?php foreach (['待处理','维修中','已完成','已报废'] as $s): ?>
                    <option value="<?= $s ?>" <?= $q_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:180px;">
            <label>搜索</label>
            <input type="text" name="q" value="<?= safe($q) ?>"
                   placeholder="设备名称、问题标题、备注…" style="width:100%;">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">搜索</button>
            <?php if ($q_status || $q): ?>
                <a class="btn" href="device_maintenance.php"
                   style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<p style="margin-bottom:12px;color:#666;">
    共 <strong><?= $total_rows ?></strong> 条记录 · 第 <?= $page ?> / <?= $total_pages ?> 页
</p>

<!-- 维修记录列表 -->
<section class="panel" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>设备</th>
                    <th>问题标题</th>
                    <th>报修人</th>
                    <th>状态</th>
                    <th>维修费用</th>
                    <th>处理人</th>
                    <th>处理时间</th>
                    <th>备注</th>
                    <?php if ($can_manage): ?><th>操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($records): ?>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= intval($r['id']) ?></td>
                    <td>
                        <strong><?= safe($r['device_name']) ?></strong>
                        <?php if ($r['device_code']): ?>
                            <br><small style="color:#888;"><?= safe($r['device_code']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= safe($r['issue_title']) ?></strong>
                        <?php if ($r['issue_description']): ?>
                            <br><small style="color:#888;font-size:12px;">
                                <?= safe(mb_strimwidth($r['issue_description'], 0, 60, '…')) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td><?= safe($r['reporter_name'] ?? '—') ?></td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;
                                     font-size:12px;font-weight:bold;color:#fff;
                                     background:<?= $status_colors[$r['status']] ?? '#888' ?>;">
                            <?= safe($r['status']) ?>
                        </span>
                    </td>
                    <td><?= $r['cost'] !== null ? 'RM ' . number_format((float)$r['cost'], 2) : '—' ?></td>
                    <td><?= safe($r['handler_name'] ?? '—') ?></td>
                    <td style="font-size:12px;color:#888;white-space:nowrap;">
                        <?= $r['handled_at'] ? safe(substr($r['handled_at'], 0, 16)) : '—' ?>
                    </td>
                    <td style="font-size:12px;color:#888;max-width:140px;">
                        <?= safe($r['note'] ?? '—') ?>
                    </td>
                    <?php if ($can_manage): ?>
                    <td style="white-space:nowrap;">
                        <?php if ($r['status'] !== '已报废' && $r['status'] !== '已完成'): ?>
                            <button class="btn-link" style="color:#5c7cfa;"
                                    onclick="openUpdateModal(
                                        <?= intval($r['id']) ?>,
                                        '<?= safe($r['device_name']) ?>',
                                        '<?= safe($r['status']) ?>',
                                        <?= $r['cost'] !== null ? floatval($r['cost']) : 'null' ?>,
                                        '<?= addslashes(safe($r['note'] ?? '')) ?>'
                                    )">
                                更新状态
                            </button>
                        <?php else: ?>
                            <span style="color:#ccc;">已完结</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $can_manage ? 10 : 9 ?>"
                        style="text-align:center;color:#888;padding:32px;">
                        <?= ($q_status||$q) ? '未找到符合条件的记录。' : '暂无维修记录。' ?>
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
            <a href="?<?= http_build_query(['status'=>$q_status,'q'=>$q,'page'=>$p]) ?>"
               style="padding:6px 12px;border-radius:6px;border:1px solid #ddd;text-decoration:none;
                      <?= $p===$page?'background:#5c7cfa;color:#fff;border-color:#5c7cfa;':'color:#333;' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<!-- 更新状态 Modal -->
<?php if ($can_manage): ?>
<div id="update-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;
            align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;width:460px;max-width:94vw;
                box-shadow:0 8px 32px rgba(0,0,0,.18);">
        <h3 style="margin:0 0 6px;">更新维修状态</h3>
        <p id="modal-device-name" style="margin:0 0 18px;color:#555;font-weight:bold;"></p>
        <form method="POST" id="update-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="maint_id" id="modal-maint-id" value="">
            <div class="form-grid">
                <div>
                    <label>更新状态 <span style="color:red">*</span></label>
                    <select name="new_status" id="modal-new-status" required>
                        <option value="待处理">待处理</option>
                        <option value="维修中">维修中</option>
                        <option value="已完成">已完成</option>
                        <option value="已报废">已报废</option>
                    </select>
                </div>
                <div>
                    <label>维修费用（RM）</label>
                    <input type="number" name="cost" id="modal-cost"
                           step="0.01" min="0" placeholder="0.00">
                </div>
                <div style="grid-column:1/-1;">
                    <label>处理备注</label>
                    <textarea name="note" id="modal-note" rows="3"
                              placeholder="维修内容、原因、操作说明等"></textarea>
                </div>
            </div>
            <div style="padding:10px 0 0;background:#fffbe6;border:1px solid #ffe58f;
                        border-radius:8px;padding:10px 14px;margin-top:12px;font-size:13px;color:#7d6608;">
                💡 状态说明：<br>
                「维修中」→ 设备状态改为「维修中」<br>
                「已完成」→ 设备状态改回「空闲」<br>
                「已报废」→ 设备状态改为「报废」
            </div>
            <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end;">
                <button type="button" onclick="closeUpdateModal()"
                        style="padding:8px 20px;border-radius:8px;border:1px solid #ddd;
                               background:#fff;cursor:pointer;">取消</button>
                <button type="submit" class="btn">确认更新</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUpdateModal(id, deviceName, currentStatus, cost, note) {
    document.getElementById('modal-maint-id').value    = id;
    document.getElementById('modal-device-name').textContent = '设备：' + deviceName;
    document.getElementById('modal-new-status').value  = currentStatus;
    document.getElementById('modal-cost').value        = (cost !== null && cost !== undefined) ? cost : '';
    document.getElementById('modal-note').value        = note || '';
    const m = document.getElementById('update-modal');
    m.style.display = 'flex';
}
function closeUpdateModal() {
    document.getElementById('update-modal').style.display = 'none';
}
document.getElementById('update-modal').addEventListener('click', function(e) {
    if (e.target === this) closeUpdateModal();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
