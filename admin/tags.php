<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin')) redirect('/myweb/login.php?redirect=/myweb/admin/tags.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['delete'])) {
        db()->prepare("DELETE FROM tags WHERE id = ?")->execute([$_POST['delete']]);
    } elseif (!empty(trim($_POST['name'] ?? ''))) {
        $stmt = db()->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
        $stmt->execute([trim($_POST['name'])]);
        if ($stmt->rowCount() === 0) $error = '标签已存在';
    }
    if (!isset($error)) redirect('/myweb/admin/tags.php');
}

$tags = db()->query("SELECT t.*, (SELECT COUNT(*) FROM article_tags WHERE tag_id=t.id) as article_count FROM tags t ORDER BY article_count DESC, t.name")->fetchAll();
?>
<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>标签管理</h2>
        <div class="admin-form-card">
            <form method="post" class="form-inline">
                <?= csrfField() ?>
                <input type="text" name="name" placeholder="标签名称" required>
                <button type="submit" class="btn btn-primary">添加</button>
            </form>
        </div>
        <?php if (empty($tags)): ?>
            <div class="empty-state" style="margin-top:20px"><p>还没有标签</p></div>
        <?php else: ?>
        <table class="table" style="margin-top:20px">
            <thead><tr><th>名称</th><th>文章数</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($tags as $t): ?>
                <tr>
                    <td><span class="tag"><?= htmlspecialchars($t['name']) ?></span></td>
                    <td><?= $t['article_count'] ?></td>
                    <td><?= deleteForm('/myweb/admin/tags.php', 'delete', $t['id'], '删除') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
