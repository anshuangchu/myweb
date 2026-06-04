<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin')) redirect('/myweb/login.php?redirect=/myweb/admin/users.php');

// 更新角色（禁止自己降级）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['role']) && hasRole('super_admin')) {
    verifyCsrf();
    if ((int)$_POST['user_id'] === (int)$_SESSION['user_id']) {
        // 不允许自己修改自己的角色
        redirect('/myweb/admin/users.php');
    }
    $stmt = db()->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$_POST['role'], $_POST['user_id']]);
    redirect('/myweb/admin/users.php');
}

// 切换状态
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle']) && hasRole('super_admin')) {
    verifyCsrf();
    $stmt = db()->prepare("UPDATE users SET status = CASE WHEN status=1 THEN 0 ELSE 1 END WHERE id = ?");
    $stmt->execute([$_POST['toggle']]);
    redirect('/myweb/admin/users.php');
}

$users = db()->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>用户管理</h2>
        <table class="table">
            <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php if (hasRole('super_admin')): ?>
                        <form method="post" style="display:inline"><?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" onchange="this.form.submit()">
                                <option value="super_admin" <?= $u['role']=='super_admin'?'selected':'' ?>>超级管理员</option>
                                <option value="admin" <?= $u['role']=='admin'?'selected':'' ?>>管理员</option>
                                <option value="editor" <?= $u['role']=='editor'?'selected':'' ?>>编辑</option>
                                <option value="user" <?= $u['role']=='user'?'selected':'' ?>>用户</option>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $u['status']?'active':'inactive' ?>"><?= $u['status']?'正常':'禁用' ?></span></td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if (hasRole('super_admin') && $u['id'] != $_SESSION['user_id']): ?>
                        <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="toggle" value="<?= $u['id'] ?>"><button type="submit" class="btn-sm"><?= $u['status']?'禁用':'启用' ?></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>
