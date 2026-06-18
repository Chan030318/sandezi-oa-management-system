<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

$message = '';
$error   = '';
$me      = current_user();

// ── 批准申请 ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    verify_csrf();
    $borrow_id = intval($_POST['borrow_id'] ?? 0);

    $chk = $pdo->prepare("SELECT * FROM device_borrows WHERE id = ? AND status = '待审批'");
    $chk->execute([$borrow_id]);
    $borrow = $chk->fetch();

    if (!$borrow) {
        $error = '申请不存在或已处理。';
    } else {
        // 再次检查冲突（其他申请可能在本次审批前已被批准）
        $conflict = $pdo->prepare("
            SELECT id FROM device_borrows
            WHERE device_id = ?
              AND id != ?
              AND status = '已批准'
              AND borrow_start <= ?
              AND borrow_end   >= ?
        ");
        $conflict->execute([
            $borrow['device_id'], $borrow_id,
            $borrow['borrow_end'], $borrow['borrow_start']
        ]);
        if ($conflict->fetch()) {
            $error = '该设备在此日期范围内已有其他已批准的借用，请先处理冲突。';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    UPDATE device_borrows
                    SET status = '已批准', approved_by = ?, approved_at = NOW()
                    WHERE id = ?
                ")->execute([$me['id'], $borrow_id]);

                $pdo->prepare("UPDATE devices SET status = '使用中' WHERE id = ?")
                    ->execute([$borrow['device_id']]);

                $pdo->commit();
                $message = '已批准借用申请，设备状态已更新为「使用中」。';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = '操作失败，请重试。';
            }
        }
    }
}

// ── 拒绝申请 ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
    verify_csrf();
    $borrow_id = intval($_POST['borrow_id'] ?? 0);

    $chk = $pdo->prepare("SELECT id FROM device_borrows WHERE id = ? AND status = '待审批'");
    $chk->execute([$borrow_id]);
    if (!$chk->fetch()) {
        $error = '申请不存在或已处理。';
    } else {
        $pdo->prepare("
            UPDATE device_borrows
            SET status = '已拒绝', approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ")->execute([$me['id'], $borrow_id]);
        $message = '已拒绝该借用申请。';
    }
}

// ── 登记归还 ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return') {
    verify_csrf();
    $borrow_id   = intval($_POST['borrow_id'] ?? 0);
    $return_note = trim($_POST['return_note'] ?? '');

    $chk = $pdo->prepare("SELECT * FROM device_borrows WHERE id = ? AND status = '已批准'");
    $chk->execute([$borrow_id]);
    $borrow = $chk->fetch();

    if (!$borrow) {
        $error = '申请不存在或状态不符（仅已批准的申请可以归还）。';
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE device_borrows
                SET status = '已归还', returned_at = NOW(), return_note = ?
                WHERE id = ?
            ")->execute([$return_note, $borrow_id]);

            $pdo->prepare("UPDATE devices SET status = '空闲' WHERE id = ?")
                ->execute([$borrow['device_id']]);

            $pdo->commit();
            $message = '设备已登记归还，状态已更新为「空闲」。';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '操作失败，请重试。';
        }
    }
}

// ── 筛选 & 分页 ─────────────────────────────────────────────────
$per_page = 25;
$page     = max(1, intval($_GET['page'] ?? 1));
$q_status = $_GET['status'] ?? '';
$q_name   = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if (in_array($q_status, ['待审批','已批准','已拒绝','已归还'], true)) {
    $where[]  = 'b.status = ?';
    $params[] = $q_status;
}
if ($q_name !== '') {
    $like     = '%' . $q_name . '%';
    $where[]  = '(d.name LIKE ? OR e.name LIKE ? OR b.purpose LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like]);
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = $pdo->prepare("
    SELECT COUNT(*)
    FROM device_borrows b
    JOIN devices   d ON b.device_id   = d.id
    LEFT JOIN employees e ON b.employee_id = e.id
    $where_sql
");
$total->execute($params);
$total_rows  = (int)$total->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT b.*,
           d.name AS device_name, d.device_code, d.status AS device_status,
           e.name AS employee_name,
           u.name AS approver_name
    FROM device_borrows b
    JOIN devices   d ON b.device_id   = d.id
    LEFT JOIN employees e ON b.employee_id = e.id
    LEFT JOIN users     u ON b.approved_by = u.id
    $where_sql
    ORDER BY
        FIELD(b.status,'待审批','已批准','已拒绝','已归还'),
        b.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$borrows = $stmt->fetchAll();

$status_colors = [
    '待审批' => '#fd7e14',
    '已批准' => '#28a745',
    '已拒绝' => '#dc3545',
    '已归还' => '#6c757d',
];

$qs_base = http_build_query(['status' => $q_status, 'q' => $q_name]);
?>

<div class="page-title">
    <h2>借用审批管理</h2>
    <p>审批员工设备借用申请、登记归还。</p>
</div>

<?php if ($message): ?><div class="success"><?= safe($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<!-- 筛选 -->
<section class="panel">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label>状态筛选</label>
            <select name="status">
                <option value="">全部状态</option>
                <?php foreach (['待审批','已批准','已拒绝','已归还'] as $s): ?>
                    <option value="<?= $s ?>" <?= $q_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:180px;">
            <label>搜索</label>
            <input type="text" name="q" value="<?= safe($q_name) ?>"
                   placeholder="设备名称、申请人、用途…" style="width:100%;">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">搜索</button>
            <?php if ($q_status || $q_name): ?>
                <a class="btn" href="device_borrow_manage.php"
                   style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- 统计 -->
<p style="margin-bottom:12px;color:#666;">
    共 <strong><?= $total_rows ?></strong> 条记录 · 第 <?= $page ?> / <?= $total_pages ?> 页
</p>

<!-- 申请列表 -->
<section class="panel" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>设备</th>
                    <th>申请人</th>
                    <th>用途</th>
                    <th>借用日期</th>
                    <th>归还日期</th>
                    <th>实际归还</th>
                    <th>状态</th>
                    <th>审批人</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($borrows): ?>
                <?php foreach ($borrows as $b): ?>
                <tr>
                    <td><?= intval($b['id']) ?></td>
                    <td>
                        <strong><?= safe($b['device_name']) ?></strong>
                        <?php if ($b['device_code']): ?>
                            <br><small style="color:#888;"><?= safe($b['device_code']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= safe($b['employee_name'] ?? '—') ?></td>
                    <td style="max-width:150px;font-size:13px;"><?= safe($b['purpose']) ?></td>
                    <td style="white-space:nowrap;"><?= safe($b['borrow_start']) ?></td>
                    <td style="white-space:nowrap;"><?= safe($b['borrow_end']) ?></td>
                    <td style="white-space:nowrap;color:#888;font-size:12px;">
                        <?= $b['returned_at'] ? safe(substr($b['returned_at'],0,10)) : '—' ?>
                        <?php if ($b['return_note']): ?>
                            <br><span title="<?= safe($b['return_note']) ?>">📝</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;
                                     font-size:12px;font-weight:bold;color:#fff;
                                     background:<?= $status_colors[$b['status']] ?? '#888' ?>;">
                            <?= safe($b['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?= safe($b['approver_name'] ?? '—') ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($b['status'] === '待审批'): ?>
                            <!-- 批准 -->
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('确定批准「<?= safe($b['device_name']) ?>」的借用申请？')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="borrow_id" value="<?= intval($b['id']) ?>">
                                <button type="submit" class="btn-link" style="color:#28a745;">批准</button>
                            </form>
                            |
                            <!-- 拒绝 -->
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('确定拒绝此申请？')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="borrow_id" value="<?= intval($b['id']) ?>">
                                <button type="submit" class="btn-link" style="color:#dc3545;">拒绝</button>
                            </form>

                        <?php elseif ($b['status'] === '已批准'): ?>
                            <!-- 登记归还 Modal 触发 -->
                            <button class="btn-link" style="color:#5c7cfa;"
                                    onclick="openReturnModal(<?= intval($b['id']) ?>, '<?= safe($b['device_name']) ?>')">
                                登记归还
                            </button>

                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align:center;color:#888;padding:32px;">
                        暂无借用记录。
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
            <a href="?<?= http_build_query(['status'=>$q_status,'q'=>$q_name,'page'=>$p]) ?>"
               style="padding:6px 12px;border-radius:6px;border:1px solid #ddd;text-decoration:none;
                      <?= $p===$page?'background:#5c7cfa;color:#fff;border-color:#5c7cfa;':'color:#333;' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<!-- 归还备注 Modal -->
<div id="return-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;
            align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;width:420px;max-width:92vw;
                box-shadow:0 8px 32px rgba(0,0,0,.18);">
        <h3 style="margin:0 0 16px;">登记归还</h3>
        <p id="return-device-name" style="margin:0 0 14px;color:#555;font-weight:bold;"></p>
        <form method="POST" id="return-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="return">
            <input type="hidden" name="borrow_id" id="return-borrow-id" value="">
            <label style="display:block;margin-bottom:6px;">归还备注（可选）</label>
            <textarea name="return_note" rows="3" style="width:100%;box-sizing:border-box;"
                      placeholder="设备状态、注意事项等"></textarea>
            <div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end;">
                <button type="button" onclick="closeReturnModal()"
                        style="padding:8px 20px;border-radius:8px;border:1px solid #ddd;
                               background:#fff;cursor:pointer;">取消</button>
                <button type="submit" class="btn">确认归还</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReturnModal(id, name) {
    document.getElementById('return-borrow-id').value = id;
    document.getElementById('return-device-name').textContent = '设备：' + name;
    const m = document.getElementById('return-modal');
    m.style.display = 'flex';
}
function closeReturnModal() {
    document.getElementById('return-modal').style.display = 'none';
}
document.getElementById('return-modal').addEventListener('click', function(e){
    if (e.target === this) closeReturnModal();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
