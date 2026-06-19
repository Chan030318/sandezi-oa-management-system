<?php
require_once __DIR__ . '/header.php';
require_role(['Admin', 'Manager']);

$message = '';
$error   = '';

// ── POST 处理 ────────────────────────────────────────────────────

// 新增员工
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();
    $name          = trim($_POST['name']          ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $position      = trim($_POST['position']      ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $email         = trim($_POST['email']         ?? '');
    $status        = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if ($name === '') {
        $error = '姓名不能为空。';
    } else {
        $pdo->prepare("
            INSERT INTO employees (name, department_id, position, phone, email, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$name, $department_id ?: null, $position, $phone, $email, $status]);
        write_audit_log('员工管理', '新增员工', "新增员工：{$name}（岗位：{$position}）");
        $message = '员工新增成功';
    }
}

// 编辑员工
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    verify_csrf();
    $id            = intval($_POST['id'] ?? 0);
    $name          = trim($_POST['name']          ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $position      = trim($_POST['position']      ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $email         = trim($_POST['email']         ?? '');
    $status        = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if ($name === '' || $id <= 0) {
        $error = '姓名不能为空。';
    } else {
        $pdo->prepare("
            UPDATE employees
            SET name = ?, department_id = ?, position = ?, phone = ?, email = ?, status = ?
            WHERE id = ?
        ")->execute([$name, $department_id ?: null, $position, $phone, $email, $status, $id]);
        write_audit_log('员工管理', '编辑员工', "编辑员工 ID {$id}：{$name}（岗位：{$position}）");
        $message = '员工资料已更新';
    }
}

// 删除员工
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $del_id = intval($_POST['id']);
    $del_row = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
    $del_row->execute([$del_id]);
    $del_name = $del_row->fetchColumn() ?: "ID {$del_id}";
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$del_id]);
    write_audit_log('员工管理', '删除员工', "删除员工：{$del_name}（ID {$del_id}）");
    $message = '员工已删除';
}

// ── 搜索 & 分页 ──────────────────────────────────────────────────
$per_page = 20;
$page     = max(1, intval($_GET['page'] ?? 1));
$q        = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($q !== '') {
    $like     = '%' . $q . '%';
    $where[]  = '(e.name LIKE ? OR e.email LIKE ? OR e.phone LIKE ? OR e.position LIKE ? OR d.name LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    $where_sql
");
$total_stmt->execute($params);
$total_rows  = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$emp_stmt = $pdo->prepare("
    SELECT e.*, d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    $where_sql
    ORDER BY e.id DESC
    LIMIT " . intval($offset) . ", " . intval($per_page));
$emp_stmt->execute($params);
$employees = $emp_stmt->fetchAll();

// ── 部门下拉 ─────────────────────────────────────────────────────
$departments = $pdo->query("SELECT * FROM departments ORDER BY id ASC")->fetchAll();

// ── 编辑表单 ─────────────────────────────────────────────────────
$editEmployee = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editEmployee = $stmt->fetch() ?: null;
}

// 分页链接辅助
$qs_base = http_build_query(['q' => $q]);
?>

<div class="page-title">
    <h2>员工管理</h2>
    <p>管理员工资料、部门、岗位与状态。</p>
</div>

<?php if ($message): ?>
    <div class="success"><?= safe($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert"><?= safe($error) ?></div>
<?php endif; ?>

<!-- 新增 / 编辑表单 -->
<section class="panel">
    <h2><?= $editEmployee ? '编辑员工' : '新增员工' ?></h2>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editEmployee ? 'edit' : 'add' ?>">
        <?php if ($editEmployee): ?>
            <input type="hidden" name="id" value="<?= intval($editEmployee['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>姓名 <span style="color:red">*</span></label>
                <input type="text" name="name" required value="<?= safe($editEmployee['name'] ?? '') ?>">
            </div>
            <div>
                <label>部门</label>
                <select name="department_id">
                    <option value="">请选择部门</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= intval($d['id']) ?>"
                            <?= (($editEmployee['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                            <?= safe($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>岗位</label>
                <input type="text" name="position" value="<?= safe($editEmployee['position'] ?? '') ?>">
            </div>
            <div>
                <label>电话</label>
                <input type="text" name="phone" value="<?= safe($editEmployee['phone'] ?? '') ?>">
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= safe($editEmployee['email'] ?? '') ?>">
            </div>
            <div>
                <label>状态</label>
                <select name="status">
                    <option value="active"   <?= (($editEmployee['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>在职</option>
                    <option value="inactive" <?= (($editEmployee['status'] ?? '')        === 'inactive') ? 'selected' : '' ?>>离职</option>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:12px;">
            <button class="btn" type="submit"><?= $editEmployee ? '保存修改' : '新增员工' ?></button>
            <?php if ($editEmployee): ?>
                <a class="btn secondary" href="employees.php?<?= $qs_base ?>">取消编辑</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- 搜索栏 -->
<section class="panel">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;">
            <label>搜索员工</label>
            <input type="text" name="q" value="<?= safe($q) ?>"
                   placeholder="姓名、Email、电话、职位、部门…" style="width:100%;">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">搜索</button>
            <?php if ($q !== ''): ?>
                <a class="btn" href="employees.php"
                   style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- 员工列表 -->
<section class="panel" style="padding:0;">
    <div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <h2 style="margin:0;">员工列表</h2>
        <span style="color:#666;font-size:14px;">
            共 <strong><?= $total_rows ?></strong> 条
            <?= $q !== '' ? '（已过滤）' : '' ?>
            · 第 <?= $page ?> / <?= $total_pages ?> 页
        </span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>部门</th>
                    <th>岗位</th>
                    <th>电话</th>
                    <th>Email</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($employees): ?>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td><?= intval($e['id']) ?></td>
                    <td><?= safe($e['name']) ?></td>
                    <td><?= safe($e['department_name'] ?? '—') ?></td>
                    <td><?= safe($e['position']) ?></td>
                    <td><?= safe($e['phone']) ?></td>
                    <td><?= safe($e['email']) ?></td>
                    <td>
                        <span class="badge <?= $e['status'] === 'active' ? '' : 'badge-inactive' ?>">
                            <?= $e['status'] === 'active' ? '在职' : '离职' ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="employees.php?edit=<?= intval($e['id']) ?>&<?= $qs_base ?>">编辑</a>
                        |
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('确定要删除「<?= safe($e['name']) ?>」吗？')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= intval($e['id']) ?>">
                            <button type="submit" class="btn-link">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:#888;padding:32px;">
                        <?= $q !== '' ? '未找到符合条件的员工。' : '暂无员工记录。' ?>
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
            <a href="?<?= http_build_query(['q' => $q, 'page' => $p]) ?>"
               style="padding:6px 12px;border-radius:6px;border:1px solid #ddd;text-decoration:none;
                      <?= $p === $page ? 'background:#5c7cfa;color:#fff;border-color:#5c7cfa;' : 'color:#333;' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
