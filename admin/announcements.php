<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin')) redirect('/myweb/login.php?redirect=/myweb/admin/announcements.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['delete'])) {
        db()->prepare("DELETE FROM announcements WHERE id = ?")->execute([$_POST['delete']]);
    } elseif (isset($_POST['content'])) {
        $content = trim($_POST['content']);
        $status = $_POST['status'] ?? 'inactive';
        $edit_id = $_POST['edit_id'] ?? '';

        if ($content) {
            if ($edit_id) {
                db()->prepare("UPDATE announcements SET content=?, status=? WHERE id=?")->execute([$content, $status, $edit_id]);
            } else {
                db()->prepare("INSERT INTO announcements (content, status) VALUES (?, ?)")->execute([$content, $status]);
            }
        }
    }
    redirect('/myweb/admin/announcements.php');
}

$annEdit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $annEdit = $stmt->fetch() ?: [];
}

$announcements = db()->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
?>
<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>公告管理</h2>
        <div class="admin-form-card">
            <form method="post" class="form-inline" style="width:100%">
                <?= csrfField() ?>
                <input type="hidden" name="edit_id" value="<?= $annEdit['id'] ?? '' ?>">
                <input type="text" name="content" placeholder="公告内容" required value="<?= htmlspecialchars($annEdit['content'] ?? '') ?>" style="flex:1;min-width:200px">
                <select name="status">
                    <option value="active" <?= ($annEdit['status']??'')=='active'?'selected':'' ?>>启用</option>
                    <option value="inactive" <?= ($annEdit['status']??'')=='inactive'?'selected':'' ?>>停用</option>
                </select>
                <button type="submit" class="btn btn-primary"><?= $annEdit ? '保存' : '添加' ?></button>
                <?php if ($annEdit): ?><a href="/myweb/admin/announcements.php" class="btn">取消</a><?php endif; ?>
            </form>
        </div>
        <?php if (empty($announcements)): ?>
            <div class="empty-state" style="margin-top:20px"><p>还没有公告</p></div>
        <?php else: ?>
        <table class="table" style="margin-top:20px">
            <thead><tr><th>内容</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($announcements as $a): ?>
                <tr>
                    <td><?= htmlspecialchars(mb_substr($a['content'], 0, 80)) ?></td>
                    <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] === 'active' ? '启用' : '停用' ?></span></td>
                    <td><?= date('Y-m-d', strtotime($a['created_at'])) ?></td>
                    <td>
                        <a href="/myweb/admin/announcements.php?edit=<?= $a['id'] ?>" class="btn-sm">编辑</a>
                        <?= deleteForm('/myweb/admin/announcements.php', 'delete', $a['id'], '删除') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
