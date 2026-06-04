<?php
$pageTitle = '用户登录';
require_once 'includes/header.php';

// 已登录用户跳转到首页
if (isLoggedIn()) {
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/myweb/"></head><body></body></html>';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isLoginThrottled()) {
        $error = '登录尝试过于频繁，请 15 分钟后再试';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            clearLoginAttempts();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $redirectUrl = $_GET['redirect'] ?? '/myweb/';
            $redirectUrl = str_replace(['\\', "\0"], ['/', ''], $redirectUrl);
            $parsed = parse_url($redirectUrl);
            $path = $parsed['path'] ?? $redirectUrl;
            $canonical = '/' . trim(str_replace(['/../', '/./', '..'], '/', '/' . $path), '/');
            if (!str_starts_with('/' . $canonical, '/myweb/')) $redirectUrl = '/myweb/'; else $redirectUrl = '/' . $canonical;
            // JS 跳转解决 header already sent 问题
            echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl) . '"></head><body></body></html>';
            exit;
        } else {
            recordLoginAttempt();
            $error = '用户名/邮箱或密码错误';
        }
    }
}
?>

<div class="auth-form">
    <h2>用户登录</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <?= csrfField() ?>
        <div class="form-group">
            <label>用户名或邮箱</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">登录</button>
        <p class="auth-link">没有账号？<a href="/myweb/register.php">去注册</a></p>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
