<?php
require_once '../includes/header.php';
if (!hasRole('super_admin')) redirect('/myweb/login.php?redirect=/myweb/admin/settings.php');

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $stmt = db()->prepare("UPDATE settings SET value = ? WHERE key_name = ?");
    $stmt->execute([trim($_POST['site_name']), 'site_name']);
    $stmt->execute([trim($_POST['site_desc']), 'site_desc']);
    // 递增版本号，使其他用户的 session 缓存失效
    $stmt = db()->prepare("INSERT INTO settings (key_name, value) VALUES ('_version', '1') ON DUPLICATE KEY UPDATE value = CAST(COALESCE(NULLIF(value, ''), '0') AS UNSIGNED) + 1");
    $stmt->execute();
    $success = '设置已保存';
    // 更新当前用户缓存
    $_SESSION['settings_cache']['site_name'] = trim($_POST['site_name']);
    $_SESSION['settings_cache']['site_desc'] = trim($_POST['site_desc']);
    $_SESSION['settings_cache_version'] = (int) db()->query("SELECT value FROM settings WHERE key_name = '_version'")->fetchColumn();
}

$site_name = $settings['site_name'] ?? '';
$site_desc = $settings['site_desc'] ?? '';
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>站点设置</h2>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label>网站名称</label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($site_name) ?>" required>
            </div>
            <div class="form-group">
                <label>网站描述</label>
                <textarea name="site_desc" rows="3"><?= htmlspecialchars($site_desc) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">保存设置</button>
        </form>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>
