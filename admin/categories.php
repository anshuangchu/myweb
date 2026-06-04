<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin')) redirect('/myweb/login.php?redirect=/myweb/admin/categories.php');

// 处理新增 / 编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    verifyCsrf();
    $editId = $_POST['edit_id'] ?? '';
    if (!empty(trim($_POST['name']))) {
        if (!empty($editId)) {
            $stmt = db()->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
            $stmt->execute([trim($_POST['name']), trim($_POST['description'] ?? ''), $editId]);
        } else {
            $stmt = db()->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([trim($_POST['name']), trim($_POST['description'] ?? '')]);
        }
    }
    redirect('/myweb/admin/categories.php');
}

// 处理删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verifyCsrf();
    $stmt = db()->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$_POST['delete']]);
    redirect('/myweb/admin/categories.php');
}

$catEdit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $catEdit = $stmt->fetch() ?: [];
}

$categories = db()->query("SELECT c.*, (SELECT COUNT(*) FROM articles WHERE category_id=c.id) as article_count FROM categories c ORDER BY name")->fetchAll();
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>分类管理</h2>
        <div class="admin-form-card">
            <form method="post" class="form-inline">
                <?= csrfField() ?>
                <input type="hidden" name="edit_id" value="<?= $catEdit['id'] ?? '' ?>">
                <input type="text" name="name" placeholder="分类名称" required value="<?= htmlspecialchars($catEdit['name'] ?? '') ?>">
                <input type="text" name="description" placeholder="分类描述" value="<?= htmlspecialchars($catEdit['description'] ?? '') ?>">
                <button type="submit" class="btn btn-primary"><?= $catEdit ? '保存' : '添加' ?></button>
                <?php if ($catEdit): ?>
                <a href="/myweb/admin/categories.php" class="btn">取消</a>
                <?php endif; ?>
            </form>
        </div>
        <table class="table" style="margin-top:20px">
            <thead><tr><th>名称</th><th>描述</th><th>文章数</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= htmlspecialchars($c['description'] ?? '') ?></td>
                    <td><?= $c['article_count'] ?></td>
                    <td>
                        <a href="/myweb/admin/categories.php?edit=<?= $c['id'] ?>" class="btn-sm">编辑</a>
                        <?= deleteForm('/myweb/admin/categories.php', 'delete', $c['id'], '删除') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>
