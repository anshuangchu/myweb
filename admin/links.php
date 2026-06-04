<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin')) redirect('/myweb/login.php?redirect=/myweb/admin/links.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['delete'])) {
        db()->prepare("DELETE FROM links WHERE id = ?")->execute([$_POST['delete']]);
    } elseif (isset($_POST['name'])) {
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $edit_id = $_POST['edit_id'] ?? '';

        if ($name && $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
                $error = '请输入有效的 HTTP/HTTPS 链接';
            } elseif ($edit_id) {
                db()->prepare("UPDATE links SET name=?, url=?, sort_order=? WHERE id=?")->execute([$name, $url, $sort_order, $edit_id]);
            } else {
                db()->prepare("INSERT INTO links (name, url, sort_order) VALUES (?, ?, ?)")->execute([$name, $url, $sort_order]);
            }
        }
    }
    redirect('/myweb/admin/links.php');
}

$linkEdit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM links WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $linkEdit = $stmt->fetch() ?: [];
}

$links = db()->query("SELECT * FROM links ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>友链管理</h2>
        <div class="admin-form-card">
            <form method="post" class="form-inline">
                <?= csrfField() ?>
                <input type="hidden" name="edit_id" value="<?= $linkEdit['id'] ?? '' ?>">
                <input type="text" name="name" placeholder="站点名称" required value="<?= htmlspecialchars($linkEdit['name'] ?? '') ?>">
                <input type="url" name="url" placeholder="https://..." required value="<?= htmlspecialchars($linkEdit['url'] ?? '') ?>">
                <input type="number" name="sort_order" placeholder="排序" value="<?= $linkEdit['sort_order'] ?? 0 ?>" style="width:80px">
                <button type="submit" class="btn btn-primary"><?= $linkEdit ? '保存' : '添加' ?></button>
                <?php if ($linkEdit): ?><a href="/myweb/admin/links.php" class="btn">取消</a><?php endif; ?>
            </form>
        </div>
        <?php if (empty($links)): ?>
            <div class="empty-state" style="margin-top:20px"><p>还没有友链</p></div>
        <?php else: ?>
        <table class="table" style="margin-top:20px">
            <thead><tr><th>名称</th><th>URL</th><th>排序</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($links as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['name']) ?></td>
                    <td><a href="<?= htmlspecialchars($l['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($l['url']) ?></a></td>
                    <td><?= $l['sort_order'] ?></td>
                    <td>
                        <a href="/myweb/admin/links.php?edit=<?= $l['id'] ?>" class="btn-sm">编辑</a>
                        <?= deleteForm('/myweb/admin/links.php', 'delete', $l['id'], '删除') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
