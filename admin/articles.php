<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin', 'editor')) redirect('/myweb/login.php?redirect=/myweb/admin/articles.php');

// POST 处理（在输出 HTML 之前处理，确保 redirect 正常工作）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole('super_admin', 'admin')) {
    verifyCsrf();
    if (isset($_POST['delete'])) {
        db()->prepare("DELETE FROM articles WHERE id = ?")->execute([$_POST['delete']]);
        redirect('/myweb/admin/articles.php');
    }
    if (isset($_POST['approve'])) {
        db()->prepare("UPDATE articles SET status='published' WHERE id=? AND status='pending'")->execute([$_POST['approve']]);
        redirect('/myweb/admin/articles.php');
    }
    if (isset($_POST['reject'])) {
        db()->prepare("UPDATE articles SET status='draft' WHERE id=? AND status='pending'")->execute([$_POST['reject']]);
        redirect('/myweb/admin/articles.php');
    }
}

$sort = $_GET['sort'] ?? 'date';
$order = sortField($sort);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$totalStmt = db()->query("SELECT COUNT(*) FROM articles");
$totalArticles = (int)$totalStmt->fetchColumn();
$totalPages = (int) max(1, ceil($totalArticles / $perPage));
$page = (int) min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->query("SELECT a.*, u.username, c.name as category_name
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    ORDER BY $order
    LIMIT $perPage OFFSET $offset");
$articles = $stmt->fetchAll();
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <div class="flex-between">
            <h2>文章管理</h2>
            <div style="display:flex;gap:8px;align-items:center">
                <span style="font-size:0.85rem;color:var(--text-secondary)">排序:</span>
                <a href="/myweb/admin/articles.php?sort=date" class="btn-sm <?= $sort==='date'?'active':'' ?>" style="<?= $sort==='date'?'background:var(--accent);color:#fff;border-color:var(--accent);':'' ?>">按日期</a>
                <a href="/myweb/admin/articles.php?sort=views" class="btn-sm <?= $sort==='views'?'active':'' ?>" style="<?= $sort==='views'?'background:var(--accent);color:#fff;border-color:var(--accent);':'' ?>">按浏览</a>
                <a href="/myweb/admin/article_edit.php" class="btn btn-primary" style="margin-left:4px">写文章</a>
            </div>
        </div>
        <?php if (empty($articles)): ?>
            <div class="empty-state"><p>还没有文章</p></div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>标题</th><th>作者</th><th>分类</th><th>👁️ 浏览</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($articles as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td><?= htmlspecialchars($a['username']) ?></td>
                    <td><?= htmlspecialchars($a['category_name'] ?? '-') ?></td>
                    <td><?= $a['views'] ?></td>
                    <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                    <td><?= date('Y-m-d', strtotime($a['created_at'])) ?></td>
                    <td>
                        <a href="/myweb/admin/article_edit.php?id=<?= $a['id'] ?>" class="btn-sm">编辑</a>
                        <?php if (hasRole('super_admin', 'admin')): ?>
                            <?php if ($a['status'] === 'pending'): ?>
                            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="approve" value="<?= $a['id'] ?>"><button type="submit" class="btn-sm" style="background:var(--success);color:#fff;border-color:var(--success)">通过</button></form>
                            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="reject" value="<?= $a['id'] ?>"><button type="submit" class="btn-sm btn-danger">拒绝</button></form>
                            <?php endif; ?>
                        <?= deleteForm('/myweb/admin/articles.php', 'delete', $a['id'], '删除') ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?= renderPagination($page, $totalPages) ?>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
