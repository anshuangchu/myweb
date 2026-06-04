<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin', 'editor')) redirect('/myweb/login.php?redirect=/myweb/admin/page_edit.php');

$id = (int)($_GET['id'] ?? 0);
$page = ['title'=>'', 'slug'=>'', 'content'=>'', 'status'=>'draft'];

if ($id) {
    $stmt = db()->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();
    if (!$page) redirect('/myweb/admin/pages.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    if (!in_array($status, ['draft', 'published'])) $status = 'draft';

    if (!$title) $error = '请输入页面标题';
    if (!$slug) $slug = mb_strtolower(trim(preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fa5}\-]+/u', '-', $title)), 'UTF-8');
    $slug = preg_replace('/-+/', '-', trim($slug, '-'));

    if (!$error) {
        if ($id) {
            $stmt = db()->prepare("UPDATE pages SET title=?, slug=?, content=?, status=? WHERE id=?");
            $stmt->execute([$title, $slug, $content, $status, $id]);
            redirect('/myweb/admin/pages.php');
        } else {
            $check = db()->prepare("SELECT id FROM pages WHERE slug=?");
            $check->execute([$slug]);
            if ($check->fetch()) {
                $slug = $slug . '-' . uniqid();
            }
            $stmt = db()->prepare("INSERT INTO pages (title, slug, content, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $status]);
            redirect('/myweb/admin/pages.php');
        }
    }
}
?>
<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2><?= $id ? '编辑资料' : '新建资料' ?></h2>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label>标题</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($page['title']) ?>">
            </div>
            <div class="form-group">
                <label>URL 别名 (slug)</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($page['slug']) ?>" placeholder="留空自动生成">
                <small style="color:var(--text-muted)">访问地址: /myweb/page.php?slug=<?= htmlspecialchars($page['slug'] ?: 'xxx') ?></small>
            </div>
            <div class="form-group">
                <label>内容 (支持HTML)</label>
                <textarea name="content" rows="15" style="font-family:monospace"><?= htmlspecialchars($page['content']) ?></textarea>
            </div>
            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="draft" <?= $page['status']=='draft'?'selected':'' ?>>草稿</option>
                    <option value="published" <?= $page['status']=='published'?'selected':'' ?>>发布</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/myweb/admin/pages.php" class="btn">取消</a>
        </form>
    </main>
</div>
<script src="/myweb/js/editor.js" defer></script>
<?php require_once '../includes/footer.php'; ?>
