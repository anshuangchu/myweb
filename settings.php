<?php
$pageTitle = '个人设置';
require_once 'includes/header.php';
require_once __DIR__ . '/includes/user_service.php';

if (!isLoggedIn()) redirect('/myweb/login.php?redirect=/myweb/settings.php');

$userId   = $_SESSION['user_id'];
$settings = UserService::getSettings($userId);
$user     = currentUser();
$message  = '';
$msgType  = 'success';

// ===== POST 处理 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $section = $_POST['section'] ?? '';

    if ($section === 'profile') {
        $data = [
            'display_name' => trim($_POST['display_name'] ?? ''),
            'bio'          => trim($_POST['bio'] ?? ''),
        ];
        // 头像上传
        if (!empty($_FILES['avatar']['tmp_name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatar = UserService::uploadAvatar($userId, $_FILES['avatar']);
            if ($avatar) {
                $data['avatar'] = $avatar;
            } else {
                $message = '头像上传失败（格式或大小不符）';
                $msgType = 'error';
            }
        }
        if (!$message) {
            UserService::updateSettings($userId, $data);
            $message = '个人资料已保存';
        }
    }

    if ($section === 'password') {
        $err = UserService::changePassword($userId, $_POST['old_password'] ?? '', $_POST['new_password'] ?? '');
        if ($err) {
            $message = $err; $msgType = 'error';
        } else {
            $message = '密码已修改，下次登录生效';
        }
    }

    // 重新加载最新数据
    $settings = UserService::getSettings($userId);
}
?>
<style>
.settings-layout { display: grid; grid-template-columns: 200px 1fr; gap: var(--sp-6); max-width: 900px; margin: var(--sp-8) auto; padding: 0 var(--sp-4); align-items: start; }
.settings-sidebar {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-md); padding: var(--sp-2) 0; position: sticky; top: calc(var(--topbar-h) + var(--sp-4));
}
.settings-sidebar a {
  display: flex; align-items: center; gap: var(--sp-2); padding: var(--sp-2) var(--sp-4);
  font-size: 0.88rem; color: var(--text-muted); text-decoration: none;
  border-left: 2px solid transparent; transition: all var(--duration-fast);
}
.settings-sidebar a:hover, .settings-sidebar a.active { color: var(--text-primary); background: var(--bg-hover); }
.settings-sidebar a.active { border-left-color: var(--accent); color: var(--accent-light); font-weight: 500; }
.settings-main { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--sp-8); }
.settings-main h3 { font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--sp-6); color: var(--text-primary); }
.settings-section { display: none; }
.settings-section.active { display: block; }
.avatar-upload { display: flex; align-items: center; gap: var(--sp-5); margin-bottom: var(--sp-6); }
.avatar-preview {
  width: 80px; height: 80px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
  background: var(--bg-hover); border: 2px solid var(--border);
  display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--text-muted);
}
.avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
</style>

<div class="settings-layout">
    <nav class="settings-sidebar">
        <a href="#profile" class="active" data-section="profile">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            个人资料
        </a>
        <a href="#password" data-section="password">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            修改密码
        </a>
    </nav>

    <main class="settings-main">
        <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>" style="margin-bottom:var(--sp-5)"><?= $message ?></div>
        <?php endif; ?>

        <!-- 个人资料 -->
        <form method="post" enctype="multipart/form-data" class="settings-section active" id="sec-profile">
            <?= csrfField() ?>
            <input type="hidden" name="section" value="profile">
            <h3>个人资料</h3>

            <div class="avatar-upload">
                <div class="avatar-preview" id="avatarPreview">
                    <?php if (!empty($settings['avatar'])): ?>
                        <img src="<?= str_starts_with($settings['avatar'],'data:') ? htmlspecialchars($settings['avatar']) : '/myweb/uploads/'.htmlspecialchars(basename($settings['avatar'])) ?>" alt="">
                    <?php else: ?>
                        <?= mb_strtoupper(mb_substr($user['username'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)">
                    <div class="text-muted text-sm" style="margin-top:4px">JPG/PNG/GIF，最大 2MB</div>
                </div>
            </div>

            <div class="form-group">
                <label>显示名称</label>
                <input type="text" name="display_name" maxlength="100" value="<?= htmlspecialchars($settings['display_name']) ?>" placeholder="<?= htmlspecialchars($user['username']) ?>">
            </div>

            <div class="form-group">
                <label>个人简介</label>
                <textarea name="bio" rows="3" maxlength="500" placeholder="介绍一下自己..."><?= htmlspecialchars($settings['bio']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">保存资料</button>
        </form>

        <!-- 修改密码 -->
        <form method="post" class="settings-section" id="sec-password">
            <?= csrfField() ?>
            <input type="hidden" name="section" value="password">
            <h3>修改密码</h3>

            <div class="form-group">
                <label>当前密码</label>
                <input type="password" name="old_password" required>
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="new_password" required minlength="8" placeholder="至少 8 位，含字母和数字">
            </div>

            <button type="submit" class="btn btn-primary">修改密码</button>
        </form>
    </main>
</div>

<script>
document.querySelectorAll('.settings-sidebar a').forEach(function(a) {
    a.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.settings-sidebar a').forEach(function(x) { x.classList.remove('active'); });
        this.classList.add('active');
        document.querySelectorAll('.settings-section').forEach(function(s) { s.classList.remove('active'); });
        document.getElementById('sec-' + this.dataset.section).classList.add('active');
    });
});

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').innerHTML = '<img src="' + e.target.result + '" alt="">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
