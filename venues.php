<?php
require_once __DIR__ . '/header.php';
$can_manage = has_role(['Admin', 'Manager']);

$message = '';
$error   = '';

// ── POST 处理（仅 Admin / Manager）──────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    $action = $_POST['action'] ?? '';

    // 新增
    if ($action === 'add') {
        verify_csrf();
        $venue_code     = trim($_POST['venue_code']     ?? '');
        $name           = trim($_POST['name']           ?? '');
        $category       = $_POST['category']            ?? '';
        $location       = trim($_POST['location']       ?? '');
        $capacity       = intval($_POST['capacity']     ?? 0) ?: null;
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_phone  = trim($_POST['contact_phone']  ?? '');
        $status_val     = $_POST['status']              ?? '可用';
        $description    = trim($_POST['description']    ?? '');

        $allowed_status   = ['可用', '占用', '维修中', '停用'];
        $allowed_category = ['直播间', '拍摄间', '会议室', '办公室', '外部场地'];
        if (!in_array($status_val, $allowed_status, true))   $status_val = '可用';
        if (!in_array($category, $allowed_category, true))   $category   = '';

        if ($name === '') {
            $error = '场地名称不能为空。';
        } elseif ($venue_code !== '') {
            $chk = $pdo->prepare("SELECT id FROM venues WHERE venue_code = ?");
            $chk->execute([$venue_code]);
            if ($chk->fetch()) $error = '场地编号已存在。';
        }

        if ($error === '') {
            $pdo->prepare("
                INSERT INTO venues
                    (venue_code, name, category, location, capacity,
                     contact_person, contact_phone, status, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $venue_code ?: null, $name, $category, $location, $capacity,
                $contact_person, $contact_phone, $status_val, $description
            ]);
            write_audit_log('场地管理', '新增场地', "新增场地：{$name}（编号：" . ($venue_code ?: '—') . "）");
            $message = '场地已新增。';
        }
    }

    // 编辑
    if ($action === 'edit') {
        verify_csrf();
        $id             = intval($_POST['id']           ?? 0);
        $venue_code     = trim($_POST['venue_code']     ?? '');
        $name           = trim($_POST['name']           ?? '');
        $category       = $_POST['category']            ?? '';
        $location       = trim($_POST['location']       ?? '');
        $capacity       = intval($_POST['capacity']     ?? 0) ?: null;
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_phone  = trim($_POST['contact_phone']  ?? '');
        $status_val     = $_POST['status']              ?? '可用';
        $description    = trim($_POST['description']    ?? '');

        $allowed_status   = ['可用', '占用', '维修中', '停用'];
        $allowed_category = ['直播间', '拍摄间', '会议室', '办公室', '外部场地'];
        if (!in_array($status_val, $allowed_status, true)) $status_val = '可用';
        if (!in_array($category, $allowed_category, true)) $category   = '';

        if ($id <= 0 || $name === '') {
            $error = '场地名称不能为空。';
        } elseif ($venue_code !== '') {
            $chk = $pdo->prepare("SELECT id FROM venues WHERE venue_code = ? AND id != ?");
            $chk->execute([$venue_code, $id]);
            if ($chk->fetch()) $error = '场地编号已被其他场地使用。';
        }

        if ($error === '') {
            $pdo->prepare("
                UPDATE venues
                SET venue_code = ?, name = ?, category = ?, location = ?, capacity = ?,
                    contact_person = ?, contact_phone = ?, status = ?, description = ?
                WHERE id = ?
            ")->execute([
                $venue_code ?: null, $name, $category, $location, $capacity,
                $contact_person, $contact_phone, $status_val, $description, $id
            ]);
            write_audit_log('场地管理', '编辑场地', "编辑场地 ID {$id}：{$name}（状态：{$status_val}）");
            $message = '场地资料已更新。';
        }
    }

    // 删除
    if ($action === 'delete') {
        verify_csrf();
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $del_row = $pdo->prepare("SELECT name, venue_code FROM venues WHERE id = ?");
            $del_row->execute([$id]);
            $del_v = $del_row->fetch();
            $pdo->prepare("DELETE FROM venues WHERE id = ?")->execute([$id]);
            $del_desc = $del_v ? "{$del_v['name']}（编号：" . ($del_v['venue_code'] ?: '—') . "）" : "ID {$id}";
            write_audit_log('场地管理', '删除场地', "删除场地：{$del_desc}");
            $message = '场地已删除。';
        }
    }
}

// ── 搜索 & 分页 ──────────────────────────────────────────────────
$per_page = 20;
$page     = max(1, intval($_GET['page']   ?? 1));
$q        = trim($_GET['q']              ?? '');
$q_cat    = trim($_GET['cat']            ?? '');
$q_status = $_GET['status']              ?? '';

$where  = [];
$params = [];

if ($q !== '') {
    $like    = '%' . $q . '%';
    $where[] = '(v.venue_code LIKE ? OR v.name LIKE ? OR v.location LIKE ?
                 OR v.contact_person LIKE ? OR v.contact_phone LIKE ?)';
    $params  = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($q_cat !== '') {
    $where[]  = 'v.category = ?';
    $params[] = $q_cat;
}
if (in_array($q_status, ['可用', '占用', '维修中', '停用'], true)) {
    $where[]  = 'v.status = ?';
    $params[] = $q_status;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM venues v $where_sql");
$total_stmt->execute($params);
$total_rows  = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$list_stmt = $pdo->prepare("
    SELECT v.*
    FROM venues v
    $where_sql
    ORDER BY v.id DESC
    LIMIT " . intval($offset) . ", " . intval($per_page));
$list_stmt->execute($params);
$venues = $list_stmt->fetchAll();

// 编辑模式
$editVenue = null;
if (isset($_GET['edit']) && $can_manage) {
    $s = $pdo->prepare("SELECT * FROM venues WHERE id = ?");
    $s->execute([intval($_GET['edit'])]);
    $editVenue = $s->fetch() ?: null;
}

$qs_base = http_build_query(['q' => $q, 'cat' => $q_cat, 'status' => $q_status]);

$status_colors = [
    '可用'  => '#28a745',
    '占用'  => '#5c7cfa',
    '维修中' => '#fd7e14',
    '停用'  => '#6c757d',
];

$categories = ['直播间', '拍摄间', '会议室', '办公室', '外部场地'];
?>

<div class="page-title">
    <h2>场地管理</h2>
    <p>管理公司直播间、拍摄间、会议室等场地资源。</p>
</div>

<?php if ($message): ?><div class="success"><?= safe($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert"><?= safe($error) ?></div><?php endif; ?>

<!-- 新增 / 编辑表单 -->
<?php if ($can_manage): ?>
<section class="panel">
    <h2><?= $editVenue ? '编辑场地' : '新增场地' ?></h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editVenue ? 'edit' : 'add' ?>">
        <?php if ($editVenue): ?>
            <input type="hidden" name="id" value="<?= intval($editVenue['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>场地名称 <span style="color:red">*</span></label>
                <input type="text" name="name" required
                       value="<?= safe($editVenue['name'] ?? '') ?>"
                       placeholder="例如：A 直播间">
            </div>
            <div>
                <label>场地编号</label>
                <input type="text" name="venue_code"
                       value="<?= safe($editVenue['venue_code'] ?? '') ?>"
                       placeholder="例如：LIVE-001">
            </div>
            <div>
                <label>场地类别</label>
                <select name="category">
                    <option value="">请选择类别</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>"
                            <?= (($editVenue['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                            <?= $cat ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>位置 / 楼层</label>
                <input type="text" name="location"
                       value="<?= safe($editVenue['location'] ?? '') ?>"
                       placeholder="例如：B1 楼、3 楼东翼">
            </div>
            <div>
                <label>容纳人数</label>
                <input type="number" name="capacity" min="0"
                       value="<?= safe($editVenue['capacity'] ?? '') ?>"
                       placeholder="例如：10">
            </div>
            <div>
                <label>负责人</label>
                <input type="text" name="contact_person"
                       value="<?= safe($editVenue['contact_person'] ?? '') ?>"
                       placeholder="姓名">
            </div>
            <div>
                <label>联系电话</label>
                <input type="text" name="contact_phone"
                       value="<?= safe($editVenue['contact_phone'] ?? '') ?>"
                       placeholder="手机 / 分机">
            </div>
            <div>
                <label>状态</label>
                <select name="status">
                    <?php foreach (['可用', '占用', '维修中', '停用'] as $s): ?>
                        <option value="<?= $s ?>"
                            <?= (($editVenue['status'] ?? '可用') === $s) ? 'selected' : '' ?>>
                            <?= $s ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:1/-1;">
                <label>备注说明</label>
                <textarea name="description" rows="2"
                          placeholder="设备配置、使用须知等"><?= safe($editVenue['description'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:14px;">
            <button class="btn" type="submit"><?= $editVenue ? '保存修改' : '新增场地' ?></button>
            <?php if ($editVenue): ?>
                <a class="btn secondary" href="venues.php?<?= $qs_base ?>">取消编辑</a>
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
                   placeholder="名称、编号、位置、负责人…" style="width:100%;">
        </div>
        <div>
            <label>类别</label>
            <select name="cat">
                <option value="">全部类别</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $q_cat===$cat?'selected':'' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>状态</label>
            <select name="status">
                <option value="">全部状态</option>
                <?php foreach (['可用', '占用', '维修中', '停用'] as $s): ?>
                    <option value="<?= $s ?>" <?= $q_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">搜索</button>
            <?php if ($q !== '' || $q_cat !== '' || $q_status !== ''): ?>
                <a class="btn" href="venues.php"
                   style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- 场地列表 -->
<section class="panel" style="padding:0;">
    <div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <h2 style="margin:0;">场地台账</h2>
        <span style="color:#666;font-size:14px;">
            共 <strong><?= $total_rows ?></strong> 个
            <?= ($q || $q_cat || $q_status) ? '（已过滤）' : '' ?>
            · 第 <?= $page ?> / <?= $total_pages ?> 页
        </span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>编号</th>
                    <th>场地名称</th>
                    <th>类别</th>
                    <th>位置</th>
                    <th>容量</th>
                    <th>负责人</th>
                    <th>联系电话</th>
                    <th>状态</th>
                    <?php if ($can_manage): ?><th>操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($venues): ?>
                <?php foreach ($venues as $v): ?>
                <tr>
                    <td><?= intval($v['id']) ?></td>
                    <td><?= safe($v['venue_code'] ?? '—') ?></td>
                    <td>
                        <strong><?= safe($v['name']) ?></strong>
                        <?php if ($v['description']): ?>
                            <br><small style="color:#888;font-size:12px;">
                                <?= safe(mb_strimwidth($v['description'], 0, 50, '…')) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td><?= safe($v['category'] ?: '—') ?></td>
                    <td><?= safe($v['location'] ?: '—') ?></td>
                    <td><?= $v['capacity'] ? safe($v['capacity']) . ' 人' : '—' ?></td>
                    <td><?= safe($v['contact_person'] ?: '—') ?></td>
                    <td><?= safe($v['contact_phone'] ?: '—') ?></td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;
                                     font-size:12px;font-weight:bold;color:#fff;
                                     background:<?= $status_colors[$v['status']] ?? '#888' ?>;">
                            <?= safe($v['status']) ?>
                        </span>
                    </td>
                    <?php if ($can_manage): ?>
                    <td style="white-space:nowrap;">
                        <a href="venues.php?edit=<?= intval($v['id']) ?>&<?= $qs_base ?>">编辑</a>
                        |
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('确定要删除「<?= safe($v['name']) ?>」吗？')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= intval($v['id']) ?>">
                            <button type="submit" class="btn-link">删除</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $can_manage ? 10 : 9 ?>"
                        style="text-align:center;color:#888;padding:32px;">
                        <?= ($q || $q_cat || $q_status) ? '未找到符合条件的场地。' : '暂无场地记录，请先新增场地。' ?>
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
