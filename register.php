<?php
$pageTitle = '用户注册';
require_once 'includes/header.php';

// 已登录用户跳转到首页
if (isLoggedIn()) {
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/myweb/"></head><body></body></html>';
    exit;
}

// 从 web 根目录外加载邀请码配置
$configDir = __DIR__ . '/../../myweb-config';
if (!file_exists($configDir . '/invite_config.php')) {
    $configDir = __DIR__ . '/../myweb-config';
}
require_once $configDir . '/invite_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // 注册频率限制：同一 IP 每小时最多 3 次
    $regAttempts = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $regAttempts->execute([getClientIP()]);
    if ($regAttempts->fetchColumn() >= 3) {
        $error = '注册请求过于频繁，请 1 小时后再试';
    }

    if (!$error) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $invite = trim($_POST['invite_code'] ?? '');

    if ($invite !== INVITE_CODE) {
        $error = '邀请码不正确';
    } elseif (strlen($username) < 2 || strlen($username) > 50) {
        $error = '用户名长度 2-50 个字符';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif (strlen($password) < 8) {
        $error = '密码至少 8 个字符';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = '密码必须包含字母和数字';
    } elseif ($password !== $confirm) {
        $error = '两次密码输入不一致';
    } else {
        $stmt = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = '用户名或邮箱已被注册';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hash]);
            $success = '注册成功！<a href="/myweb/login.php">点击登录</a>';
        }
    }
    } // end if (!$error)
}
?>

<div class="auth-page-bg"><div class="auth-form">
    <h2>用户注册</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if (!$success): ?>
    <form method="post">
        <?= csrfField() ?>
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" required minlength="2" maxlength="50" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>邮箱</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
            <label>确认密码</label>
            <input type="password" name="confirm_password" required minlength="8">
        </div>
        <div class="form-group">
            <label>邀请码</label>
            <input type="text" name="invite_code" required placeholder="请输入邀请码" value="<?= htmlspecialchars($_POST['invite_code'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">注册</button>
        <p class="auth-link">已有账号？<a href="/myweb/login.php">去登录</a></p>
    </form>
    <?php endif; ?>
</div>

</div><?php require_once 'includes/footer.php'; ?>
