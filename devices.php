<?php
require_once __DIR__ . '/header.php';
// Admin / Manager：全部操作；Employee：只读
$can_manage = has_role(['Admin', 'Manager']);

$message = '';
$error   = '';

// ── POST 处理（仅 Admin / Manager）──────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    $action = $_POST['action'] ?? '';

    // 新增
    if ($action === 'add') {
        verify_csrf();
        $device_code   = trim($_POST['device_code']   ?? '');
        $asset_code    = trim($_POST['asset_code']    ?? '');
        $name          = trim($_POST['name']          ?? '');
        $category      = trim($_POST['category']      ?? '');
        $brand         = trim($_POST['brand']         ?? '');
        $model         = trim($_POST['model']         ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $department_id = intval($_POST['department_id'] ?? 0) ?: null;
        $manager       = trim($_POST['manager']       ?? '');
        $status_val    = $_POST['status'] ?? '空闲';
        $allowed_status = ['空闲', '使用中', '维修中', '报废'];
        if (!in_array($status_val, $allowed_status, true)) $status_val = '空闲';

        if ($name === '') {
            $error = '设备名称不能为空。';
        } elseif ($device_code !== '') {
            $chk = $pdo->prepare("SELECT id FROM devices WHERE device_code = ?");
            $chk->execute([$device_code]);
            if ($chk->fetch()) { $error = '设备编号已存在。'; }
        }

        if ($error === '') {
            $pdo->prepare("
                INSERT INTO devices
                    (device_code, asset_code, name, category, brand, model,
                     serial_number, department_id, manager, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $device_code ?: null, $asset_code ?: null, $name,
                $category, $brand, $model, $serial_number ?: null,
                $department_id, $manager, $status_val
            ]);
            write_audit_log('设备管理', '新增设备', "新增设备：{$name}（编号：" . ($device_code ?: '—') . "）");
            $message = '设备已新增。';
        }
    }

    // 编辑
    if ($action === 'edit') {
        verify_csrf();
        $id            = intval($_POST['id'] ?? 0);
        $device_code   = trim($_POST['device_code']   ?? '');
        $asset_code    = trim($_POST['asset_code']    ?? '');
        $name          = trim($_POST['name']          ?? '');
        $category      = trim($_POST['category']      ?? '');
        $brand         = trim($_POST['brand']         ?? '');
        $model         = trim($_POST['model']         ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $department_id = intval($_POST['department_id'] ?? 0) ?: null;
        $manager       = trim($_POST['manager']       ?? '');
        $status_val    = $_POST['status'] ?? '空闲';
        $allowed_status = ['空闲', '使用中', '维修中', '报废'];
        if (!in_array($status_val, $allowed_status, true)) $status_val = '空闲';

        if ($id <= 0 || $name === '') {
            $error = '设备名称不能为空。';
        } else {
            if ($device_code !== '') {
                $chk = $pdo->prepare("SELECT id FROM devices WHERE device_code = ? AND id != ?");
                $chk->execute([$device_code, $id]);
                if ($chk->fetch()) { $error = '设备编号已被其他设备使用。'; }
            }
        }

        if ($error === '') {
            $pdo->prepare("
                UPDATE devices
                SET device_code = ?, asset_code = ?, name = ?, category = ?,
                    brand = ?, model = ?, serial_number = ?,
                    department_id = ?, manager = ?, status = ?
                WHERE id = ?
            ")->execute([
                $device_code ?: null, $asset_code ?: null, $name,
                $category, $brand, $model, $serial_number ?: null,
                $department_id, $manager, $status_val, $id
            ]);
            write_audit_log('设备管理', '编辑设备', "编辑设备 ID {$id}：{$name}（状态：{$status_val}）");
            $message = '设备资料已更新。';
        }
    }

    // 删除
    if ($action === 'delete') {
        verify_csrf();
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $del_row = $pdo->prepare("SELECT name, device_code FROM devices WHERE id = ?");
            $del_row->execute([$id]);
            $del_dv = $del_row->fetch();
            $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$id]);
            $del_desc = $del_dv ? "{$del_dv['name']}（编号：" . ($del_dv['device_code'] ?: '—') . "）" : "ID {$id}";
            write_audit_log('设备管理', '删除设备', "删除设备：{$del_desc}");
            $message = '设备已删除。';
        }
    }
}

// ── 搜索 & 分页 ──────────────────────────────────────────────────
$per_page = 20;
$page     = max(1, intval($_GET['page'] ?? 1));
$q        = trim($_GET['q']      ?? '');
$q_cat    = trim($_GET['cat']    ?? '');
$q_status = $_GET['status']      ?? '';

$where  = [];
$params = [];

if ($q !== '') {
    $like     = '%' . $q . '%';
    $where[]  = '(d.device_code LIKE ? OR d.asset_code LIKE ? OR d.name LIKE ?
                  OR d.brand LIKE ? OR d.model LIKE ? OR d.serial_number LIKE ? OR d.manager LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}
if ($q_cat !== '') {
    $where[]  = 'd.category = ?';
    $params[] = $q_cat;
}
if (in_array($q_status, ['空闲','使用中','维修中','报废'], true)) {
    $where[]  = 'd.status = ?';
    $params[] = $q_status;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM devices d $where_sql
");
$total_stmt->execute($params);
$total_rows  = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$list_stmt = $pdo->prepare("
    SELECT d.*, dept.name AS department_name
    FROM devices d
    LEFT JOIN departments dept ON d.department_id = dept.id
    $where_sql
    ORDER BY d.id DESC
    LIMIT ?, ?
");
$list_stmt->execute(array_merge($params, [$offset, $per_page]));
$devices = $list_stmt->fetchAll();

// 分类列表（下拉筛选用）
$categories = $pdo->query("SELECT DISTINCT category FROM devices WHERE category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// 部门列表（表单用）
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY id ASC")->fetchAll();

// 编辑模式
$editDevice = null;
if (isset($_GET['edit']) && $can_manage) {
    $s = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
    $s->execute([intval($_GET['edit'])]);
    $editDevice = $s->fetch() ?: null;
}

$qs_base = http_build_query(['q' => $q, 'cat' => $q_cat, 'status' => $q_status]);

// 状态样式
$status_colors = [
    '空闲'  => '#28a745',
    '使用中' => '#5c7cfa',
    '维修中' => '#fd7e14',
    '报废'  => '#6c757d',
];
?>

<div class="page-title">
    <h2>设备管理</h2>
    <p>管理公司直播设备、办公设备台账。</p>
</div>

<?php if ($message): ?><div class="success"><?= safe($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<!-- 新增 / 编辑表单 -->
<?php if ($can_manage): ?>
<section class="panel">
    <h2><?= $editDevice ? '编辑设备' : '新增设备' ?></h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editDevice ? 'edit' : 'add' ?>">
        <?php if ($editDevice): ?>
            <input type="hidden" name="id" value="<?= intval($editDevice['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>设备名称 <span style="color:red">*</span></label>
                <input type="text" name="name" required
                       value="<?= safe($editDevice['name'] ?? '') ?>"
                       placeholder="例如：索尼摄影机 A7III">
            </div>
            <div>
                <label>设备编号</label>
                <input type="text" name="device_code"
                       value="<?= safe($editDevice['device_code'] ?? '') ?>"
                       placeholder="例如：CAM-001">
            </div>
            <div>
                <label>资产编号</label>
                <input type="text" name="asset_code"
                       value="<?= safe($editDevice['asset_code'] ?? '') ?>"
                       placeholder="财务固定资产编号">
            </div>
            <div>
                <label>设备类别</label>
                <input type="text" name="category"
                       value="<?= safe($editDevice['category'] ?? '') ?>"
                       placeholder="例如：摄影设备、电脑、灯光">
            </div>
            <div>
                <label>品牌</label>
                <input type="text" name="brand"
                       value="<?= safe($editDevice['brand'] ?? '') ?>"
                       placeholder="例如：Sony、Apple">
            </div>
            <div>
                <label>型号</label>
                <input type="text" name="model"
                       value="<?= safe($editDevice['model'] ?? '') ?>"
                       placeholder="例如：A7III">
            </div>
            <div>
                <label>序列号 / SN</label>
                <input type="text" name="serial_number"
                       value="<?= safe($editDevice['serial_number'] ?? '') ?>"
                       placeholder="设备序列号">
            </div>
            <div>
                <label>所属部门</label>
                <select name="department_id">
                    <option value="">请选择部门</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= intval($dept['id']) ?>"
                            <?= (($editDevice['department_id'] ?? '') == $dept['id']) ? 'selected' : '' ?>>
                            <?= safe($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>负责人</label>
                <input type="text" name="manager"
                       value="<?= safe($editDevice['manager'] ?? '') ?>"
                       placeholder="姓名">
            </div>
            <div>
                <label>状态</label>
                <select name="status">
                    <?php foreach (['空闲','使用中','维修中','报废'] as $s): ?>
                        <option value="<?= $s ?>"
                            <?= (($editDevice['status'] ?? '空闲') === $s) ? 'selected' : '' ?>>
                            <?= $s ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:14px;">
            <button class="btn" type="submit"><?= $editDevice ? '保存修改' : '新增设备' ?></button>
            <?php if ($editDevice): ?>
                <a class="btn secondary" href="devices.php?<?= $qs_base ?>">取消编辑</a>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<!-- 搜索 & 筛选 -->
<section class="panel">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:180px;">
            <label>搜索</label>
            <input type="text" name="q" value="<?= safe($q) ?>"
                   placeholder="名称、编号、品牌、型号、负责人…" style="width:100%;">
        </div>
        <div>
            <label>类别</label>
            <select name="cat">
                <option value="">全部类别</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= safe($cat) ?>" <?= $q_cat===$cat?'selected':'' ?>>
                        <?= safe($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>状态</label>
            <select name="status">
                <option value="">全部状态</option>
                <?php foreach (['空闲','使用中','维修中','报废'] as $s): ?>
                    <option value="<?= $s ?>" <?= $q_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">搜索</button>
            <?php if ($q !== '' || $q_cat !== '' || $q_status !== ''): ?>
                <a class="btn" href="devices.php"
                   style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- 设备列表 -->
<section class="panel" style="padding:0;">
    <div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <h2 style="margin:0;">设备台账</h2>
        <span style="color:#666;font-size:14px;">
            共 <strong><?= $total_rows ?></strong> 台
            <?= ($q||$q_cat||$q_status) ? '（已过滤）' : '' ?>
            · 第 <?= $page ?> / <?= $total_pages ?> 页
        </span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>设备编号</th>
                    <th>资产编号</th>
                    <th>设备名称</th>
                    <th>类别</th>
                    <th>品牌 / 型号</th>
                    <th>序列号</th>
                    <th>所属部门</th>
                    <th>负责人</th>
                    <th>状态</th>
                    <?php if ($can_manage): ?><th>操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($devices): ?>
                <?php foreach ($devices as $dv): ?>
                <tr>
                    <td><?= intval($dv['id']) ?></td>
                    <td><?= safe($dv['device_code'] ?? '—') ?></td>
                    <td><?= safe($dv['asset_code']  ?? '—') ?></td>
                    <td><strong><?= safe($dv['name']) ?></strong></td>
                    <td><?= safe($dv['category'] ?: '—') ?></td>
                    <td><?= safe(trim($dv['brand'] . ' ' . $dv['model']) ?: '—') ?></td>
                    <td style="font-size:12px;color:#888;"><?= safe($dv['serial_number'] ?? '—') ?></td>
                    <td><?= safe($dv['department_name'] ?? '—') ?></td>
                    <td><?= safe($dv['manager'] ?: '—') ?></td>
                    <td>
                        <span style="
                            display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;
                            font-weight:bold;color:#fff;
                            background:<?= $status_colors[$dv['status']] ?? '#888' ?>;">
                            <?= safe($dv['status']) ?>
                        </span>
                    </td>
                    <?php if ($can_manage): ?>
                    <td style="white-space:nowrap;">
                        <a href="devices.php?edit=<?= intval($dv['id']) ?>&<?= $qs_base ?>">编辑</a>
                        |
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('确定要删除「<?= safe($dv['name']) ?>」吗？')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= intval($dv['id']) ?>">
                            <button type="submit" class="btn-link">删除</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $can_manage ? 11 : 10 ?>"
                        style="text-align:center;color:#888;padding:32px;">
                        <?= ($q||$q_cat||$q_status) ? '未找到符合条件的设备。' : '暂无设备记录，请先新增设备。' ?>
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
            <a href="?<?= http_build_query(['q'=>$q,'cat'=>$q_cat,'status'=>$q_status,'page'=>$p]) ?>"
               style="padding:6px 12px;border-radius:6px;border:1px solid #ddd;text-decoration:none;
                      <?= $p===$page?'background:#5c7cfa;color:#fff;border-color:#5c7cfa;':'color:#333;' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
