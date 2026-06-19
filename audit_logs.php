<?php
require_once __DIR__ . '/header.php';
require_role(['Admin']);

// ── 筛选参数 ─────────────────────────────────────────────────────
$f_module    = trim($_GET['module']     ?? '');
$f_action    = trim($_GET['action']     ?? '');
$f_user      = trim($_GET['user']       ?? '');
$f_date_from = trim($_GET['date_from']  ?? '');
$f_date_to   = trim($_GET['date_to']    ?? '');

$per_page = 25;
$page     = max(1, intval($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($f_module !== '') {
    $where[]  = 'a.module = ?';
    $params[] = $f_module;
}
if ($f_action !== '') {
    $where[]  = 'a.action = ?';
    $params[] = $f_action;
}
if ($f_user !== '') {
    $like     = '%' . $f_user . '%';
    $where[]  = 'u.name LIKE ?';
    $params[] = $like;
}
if ($f_date_from !== '') {
    $where[]  = 'DATE(a.created_at) >= ?';
    $params[] = $f_date_from;
}
if ($f_date_to !== '') {
    $where[]  = 'DATE(a.created_at) <= ?';
    $params[] = $f_date_to;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    $where_sql
");
$total_stmt->execute($params);
$total_rows  = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$list_stmt = $pdo->prepare("
    SELECT a.*, u.name AS user_name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    $where_sql
    ORDER BY a.id DESC
    LIMIT " . intval($offset) . ", " . intval($per_page));
$list_stmt->execute($params);
$logs = $list_stmt->fetchAll();

// 下拉筛选用：所有 module 和 action 枚举
$modules = $pdo->query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

$qs_base = http_build_query([
    'module'    => $f_module,
    'action'    => $f_action,
    'user'      => $f_user,
    'date_from' => $f_date_from,
    'date_to'   => $f_date_to,
]);
?>

<div class="page-title">
    <h2>操作日志</h2>
    <p>记录系统内所有关键写操作的操作人、模块、动作与时间。</p>
</div>

<!-- 筛选 -->
<section class="panel">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label>模块</label>
            <select name="module">
                <option value="">全部模块</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= safe($m) ?>" <?= $f_module===$m?'selected':'' ?>><?= safe($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>操作</label>
            <select name="action">
                <option value="">全部操作</option>
                <?php foreach ($actions as $ac): ?>
                    <option value="<?= safe($ac) ?>" <?= $f_action===$ac?'selected':'' ?>><?= safe($ac) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width:140px;">
            <label>操作人</label>
            <input type="text" name="user" value="<?= safe($f_user) ?>" placeholder="用户名称" style="width:100%;">
        </div>
        <div>
            <label>日期从</label>
            <input type="date" name="date_from" value="<?= safe($f_date_from) ?>">
        </div>
        <div>
            <label>日期到</label>
            <input type="date" name="date_to" value="<?= safe($f_date_to) ?>">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">筛选</button>
            <?php if ($f_module || $f_action || $f_user || $f_date_from || $f_date_to): ?>
                <a class="btn" href="audit_logs.php"
                   style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<p style="margin-bottom:12px;color:#666;">
    共 <strong><?= $total_rows ?></strong> 条记录 · 第 <?= $page ?> / <?= $total_pages ?> 页
</p>

<!-- 日志列表 -->
<section class="panel" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>时间</th>
                    <th>操作人</th>
                    <th>模块</th>
                    <th>动作</th>
                    <th>说明</th>
                    <th>IP 地址</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="color:#aaa;font-size:12px;"><?= intval($log['id']) ?></td>
                    <td style="white-space:nowrap;font-size:12px;color:#888;">
                        <?= safe(substr($log['created_at'], 0, 16)) ?>
                    </td>
                    <td><?= safe($log['user_name'] ?? '—') ?></td>
                    <td><span class="badge"><?= safe($log['module']) ?></span></td>
                    <td style="font-weight:600;"><?= safe($log['action']) ?></td>
                    <td style="font-size:13px;color:#444;"><?= safe($log['description']) ?></td>
                    <td style="font-size:12px;color:#aaa;"><?= safe($log['ip_address'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:#888;padding:32px;">
                        <?= ($f_module||$f_action||$f_user||$f_date_from||$f_date_to) ? '未找到符合条件的记录。' : '暂无操作日志。' ?>
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
            <a href="?<?= http_build_query(['module'=>$f_module,'action'=>$f_action,'user'=>$f_user,'date_from'=>$f_date_from,'date_to'=>$f_date_to,'page'=>$p]) ?>"
               style="padding:6px 12px;border-radius:6px;border:1px solid #ddd;text-decoration:none;
                      <?= $p===$page?'background:#5c7cfa;color:#fff;border-color:#5c7cfa;':'color:#333;' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
