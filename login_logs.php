<?php
require_once __DIR__ . '/header.php';
require_role(['Admin']);

$per_page    = 25;
$page        = max(1, intval($_GET['page'] ?? 1));
$q_email     = trim($_GET['email'] ?? '');
$q_ip        = trim($_GET['ip']    ?? '');
$q_status    = $_GET['status'] ?? '';

// 构造动态 WHERE
$where  = [];
$params = [];

if ($q_email !== '') {
    $where[]  = 'l.email LIKE ?';
    $params[] = '%' . $q_email . '%';
}
if ($q_ip !== '') {
    $where[]  = 'l.ip LIKE ?';
    $params[] = '%' . $q_ip . '%';
}
if (in_array($q_status, ['success', 'failed'], true)) {
    $where[]  = 'l.status = ?';
    $params[] = $q_status;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// 总数
$total = $pdo->prepare("SELECT COUNT(*) FROM login_logs l $where_sql");
$total->execute($params);
$total_rows  = (int)$total->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// 分页数据
$stmt = $pdo->prepare("
    SELECT l.id, l.email, l.user_id, l.ip, l.user_agent, l.status, l.created_at,
           u.name AS user_name
    FROM login_logs l
    LEFT JOIN users u ON l.user_id = u.id
    $where_sql
    ORDER BY l.id DESC
    LIMIT " . intval($offset) . ", " . intval($per_page));
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="page-title">
    <h2>登录日志</h2>
    <p>记录所有用户的登录尝试（成功与失败）。</p>
</div>

<!-- 搜索栏 -->
<section class="panel">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label>邮箱</label>
            <input type="text" name="email" placeholder="搜索邮箱"
                   value="<?= safe($q_email) ?>" style="width:200px;">
        </div>
        <div>
            <label>IP 地址</label>
            <input type="text" name="ip" placeholder="搜索 IP"
                   value="<?= safe($q_ip) ?>" style="width:160px;">
        </div>
        <div>
            <label>状态</label>
            <select name="status">
                <option value="">全部</option>
                <option value="success" <?= $q_status==='success'?'selected':'' ?>>成功</option>
                <option value="failed"  <?= $q_status==='failed' ?'selected':'' ?>>失败</option>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">搜索</button>
            <a class="btn" href="login_logs.php"
               style="background:#6c757d;text-decoration:none;padding:8px 16px;">清除</a>
        </div>
    </form>
</section>

<!-- 统计 -->
<p style="margin-bottom:12px;color:#666;">
    共 <strong><?= $total_rows ?></strong> 条记录，当前第 <?= $page ?> / <?= $total_pages ?> 页
</p>

<!-- 日志表格 -->
<section class="panel" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>时间</th>
                    <th>邮箱</th>
                    <th>用户名</th>
                    <th>状态</th>
                    <th>IP 地址</th>
                    <th>User-Agent</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= $log['id'] ?></td>
                    <td style="white-space:nowrap;"><?= safe($log['created_at']) ?></td>
                    <td><?= safe($log['email']) ?></td>
                    <td><?= $log['user_name'] ? safe($log['user_name']) : '<span style="color:#aaa">—</span>' ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span style="color:#28a745;font-weight:bold;">✓ 成功</span>
                        <?php else: ?>
                            <span style="color:#dc3545;font-weight:bold;">✗ 失败</span>
                        <?php endif; ?>
                    </td>
                    <td><?= safe($log['ip']) ?></td>
                    <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#888;"
                        title="<?= safe($log['user_agent']) ?>">
                        <?= safe($log['user_agent']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;color:#888;padding:32px;">无记录</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- 分页 -->
<?php if ($total_pages > 1): ?>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:16px;">
    <?php
    $qs = http_build_query(['email'=>$q_email,'ip'=>$q_ip,'status'=>$q_status]);
    for ($p = 1; $p <= $total_pages; $p++):
    ?>
        <a href="?<?= $qs ?>&page=<?= $p ?>"
           style="padding:6px 12px;border-radius:6px;border:1px solid #ddd;text-decoration:none;
                  <?= $p===$page ? 'background:#5c7cfa;color:#fff;border-color:#5c7cfa;' : 'color:#333;' ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
