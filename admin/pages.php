<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin', 'editor')) redirect('/myweb/login.php?redirect=/myweb/admin/pages.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && hasRole('super_admin', 'admin')) {
    verifyCsrf();
    db()->prepare("DELETE FROM pages WHERE id = ?")->execute([$_POST['delete']]);
    redirect('/myweb/admin/pages.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$totalStmt = db()->query("SELECT COUNT(*) FROM pages");
$totalPages = (int) max(1, ceil((int)$totalStmt->fetchColumn() / $perPage));
$page = (int) min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$pages = db()->query("SELECT * FROM pages ORDER BY created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
?>
<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <div class="flex-between">
            <h2>资料管理</h2>
            <a href="/myweb/admin/page_edit.php" class="btn btn-primary">新建页面</a>
        </div>
        <?php if (empty($pages)): ?>
            <div class="empty-state"><p>还没有页面</p></div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>标题</th><th>路径</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['title']) ?></td>
                    <td><a href="/myweb/page.php?slug=<?= urlencode($p['slug']) ?>" target="_blank">/<?= htmlspecialchars($p['slug']) ?></a></td>
                    <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
                    <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
                    <td>
                        <a href="/myweb/admin/page_edit.php?id=<?= $p['id'] ?>" class="btn-sm">编辑</a>
                        <?php if (hasRole('super_admin', 'admin')): ?>
                        <?= deleteForm('/myweb/admin/pages.php', 'delete', $p['id'], '删除') ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?= renderPagination($page, $totalPages, '/myweb/admin/pages.php?page=%d') ?>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
