<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin', 'editor')) redirect('/myweb/login.php?redirect=/myweb/admin/');

$articleCount = db()->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$userCount = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$publishedCount = db()->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();
$pendingCount = db()->query("SELECT COUNT(*) FROM articles WHERE status='pending'")->fetchColumn();
$linkCount = db()->query("SELECT COUNT(*) FROM links")->fetchColumn();
$annCount = db()->query("SELECT COUNT(*) FROM announcements WHERE status='active'")->fetchColumn();
$recentArticles = db()->query("SELECT a.*, u.username FROM articles a LEFT JOIN users u ON a.author_id = u.id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>控制台</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $publishedCount ?></h3>
                <p>已发布文章</p>
            </div>
            <div class="stat-card">
                <h3><?= $pendingCount ?></h3>
                <p>待审核文章</p>
            </div>
            <div class="stat-card">
                <h3><?= $articleCount ?></h3>
                <p>全部文章</p>
            </div>
            <div class="stat-card">
                <h3><?= $userCount ?></h3>
                <p>注册用户</p>
            </div>
            <div class="stat-card">
                <h3><?= $linkCount ?></h3>
                <p>友链</p>
            </div>
            <div class="stat-card">
                <h3><?= $annCount ?></h3>
                <p>启用公告</p>
            </div>
        </div>
        <h3>最近文章</h3>
        <table class="table">
            <thead><tr><th>标题</th><th>作者</th><th>👁️ 浏览</th><th>状态</th><th>时间</th></tr></thead>
            <tbody>
                <?php foreach ($recentArticles as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td><?= htmlspecialchars($a['username']) ?></td>
                    <td><?= $a['views'] ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                    <td><?= date('Y-m-d', strtotime($a['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>
