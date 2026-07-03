<?php
$pageTitle = '好友';
require_once 'includes/header.php';
require_once __DIR__ . '/includes/social_service.php';

if (!isLoggedIn()) redirect('/myweb/login.php?redirect=/myweb/friends.php');

$userId    = (int) $_SESSION['user_id'];
$partnerId = (int)($_GET['u'] ?? 0);
$tab       = $_GET['tab'] ?? 'friends'; // friends | requests | search
$message   = '';
$msgType   = 'success';

// ===== 发送好友请求 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_friend'])) {
    verifyCsrf();
    $err = FriendService::sendRequest($userId, (int)($_POST['add_friend'] ?? 0));
    $message = $err ?: '好友请求已发送';
    if ($err) $msgType = 'error';
}

// ===== 通过/拒绝 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
    verifyCsrf();
    FriendService::accept((int)$_POST['accept'], $userId);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    verifyCsrf();
    FriendService::remove($userId, (int)$_POST['reject']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove'])) {
    verifyCsrf();
    FriendService::remove($userId, (int)$_POST['remove']);
}

// ===== 发消息 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_id'])) {
    verifyCsrf();
    $toId = (int)($_POST['to_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $hasImage = !empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
    if ($toId) {
        if ($hasImage) {
            $err = MessageService::sendAttachment($userId, $toId, $_FILES['image']);
            if ($err) { $message = $err; $msgType = 'error'; }
        } elseif ($content) {
            MessageService::send($userId, $toId, $content);
        }
        if (!$message) redirect('/myweb/friends.php?u=' . $toId);
    }
}

// ===== 加载数据 =====
$friends    = FriendService::getFriends($userId);
$pending    = FriendService::getPendingRequests($userId);
$searchQ    = trim($_GET['q'] ?? '');
$searchResults = $searchQ ? FriendService::searchUsers($searchQ, $userId) : [];

// 聊天数据
$messages = [];
$partner  = null;
if ($partnerId) {
    $messages = array_reverse(MessageService::getConversation($userId, $partnerId));
    $stmt = db()->prepare("SELECT id,username,COALESCE((SELECT display_name FROM user_settings WHERE user_id=users.id),username) AS display_name, (SELECT avatar FROM user_settings WHERE user_id=users.id) AS avatar FROM users WHERE id=?");
    $stmt->execute([$partnerId]);
    $partner = $stmt->fetch();
}
?>

<div class="social-full">
    <!-- 左侧栏 — 联系人 -->
    <div class="social-sidebar">
        <div class="social-sidebar-hd">
            <h3>好友</h3>
            <div class="social-tabs">
                <a href="?tab=friends<?= $partnerId?'&u='.$partnerId:'' ?>" class="social-tab <?= $tab==='friends'?'active':'' ?>">联系人</a>
                <a href="?tab=requests<?= $partnerId?'&u='.$partnerId:'' ?>" class="social-tab <?= $tab==='requests'?'active':'' ?>">
                    请求 <?php if($pending): ?><span class="social-dot"></span><?php endif; ?>
                </a>
                <a href="?tab=search<?= $partnerId?'&u='.$partnerId:'' ?>" class="social-tab <?= $tab==='search'?'active':'' ?>">添加</a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>" style="margin:var(--sp-2) var(--sp-3);padding:var(--sp-2) var(--sp-3);font-size:0.82rem"><?= $message ?></div>
        <?php endif; ?>

        <div class="social-sidebar-list">
            <?php if ($tab === 'friends'): ?>
                <?php if (empty($friends)): ?>
                <div class="social-empty">暂无好友，去「添加」搜索</div>
                <?php else: ?>
                <?php foreach ($friends as $f): ?>
                <a href="?tab=friends&u=<?= $f['id'] ?>" class="social-contact <?= $partnerId===(int)$f['id']?'active':'' ?>">
                    <span class="social-avatar">
                        <?php if (!empty($f['avatar'])): ?>
                        <img src="<?= str_starts_with($f['avatar'],'data:') ? htmlspecialchars($f['avatar']) : '/myweb/uploads/'.htmlspecialchars(basename($f['avatar'])) ?>" alt="">
                        <?php else: ?>
                        <?= mb_strtoupper(mb_substr($f['display_name'],0,1)) ?>
                        <?php endif; ?>
                    </span>
                    <div class="social-contact-info">
                        <span class="social-contact-name"><?= htmlspecialchars($f['display_name']) ?></span>
                        <span class="social-contact-user">@<?= htmlspecialchars($f['username']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>

            <?php elseif ($tab === 'requests'): ?>
                <?php if (empty($pending)): ?>
                <div class="social-empty">没有待处理的请求</div>
                <?php else: ?>
                <?php foreach ($pending as $p): ?>
                <div class="social-request-card" onclick="this.classList.toggle('expanded')">
                    <div class="social-request-summary">
                        <span class="social-avatar">
                            <?php if (!empty($p['avatar'])): ?>
                            <img src="<?= str_starts_with($p['avatar'],'data:') ? htmlspecialchars($p['avatar']) : '/myweb/uploads/'.htmlspecialchars(basename($p['avatar'])) ?>" alt="">
                            <?php else: ?>
                            <?= mb_strtoupper(mb_substr($p['display_name'],0,1)) ?>
                            <?php endif; ?>
                        </span>
                        <div class="social-request-info">
                            <span class="social-request-name"><?= htmlspecialchars($p['display_name']) ?></span>
                            <span class="social-request-user">@<?= htmlspecialchars($p['username']) ?></span>
                        </div>
                        <div class="social-request-actions">
                            <form method="post"><?= csrfField() ?><input type="hidden" name="accept" value="<?= $p['user_id'] ?>"><button class="btn-sm btn-edit">✓ 通过</button></form>
                            <form method="post"><?= csrfField() ?><input type="hidden" name="reject" value="<?= $p['user_id'] ?>"><button class="btn-sm btn-danger">✕ 拒绝</button></form>
                        </div>
                    </div>
                    <div class="social-request-detail">
                        <?php if ($p['bio']): ?>
                        <div class="social-request-bio"><?= htmlspecialchars($p['bio']) ?></div>
                        <?php else: ?>
                        <div class="social-request-bio" style="color:var(--text-muted);font-style:italic">暂无个人简介</div>
                        <?php endif; ?>
                        <div class="social-request-meta">
                            <span>📧 <?= htmlspecialchars($p['email']) ?></span>
                            <span>🕒 <?= date('Y-m-d H:i', strtotime($p['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

            <?php else: ?>
                <form class="social-search-form" method="get">
                    <input type="hidden" name="tab" value="search">
                    <input type="text" name="q" placeholder="搜索用户..." value="<?= htmlspecialchars($searchQ) ?>" autofocus>
                </form>
                <?php if ($searchResults): ?>
                <?php foreach ($searchResults as $u): ?>
                <div class="social-contact">
                    <span class="social-avatar"><?= mb_strtoupper(mb_substr($u['display_name'],0,1)) ?></span>
                    <span class="social-contact-name"><?= htmlspecialchars($u['display_name']) ?></span>
                    <form method="post" style="margin-left:auto"><?= csrfField() ?><input type="hidden" name="add_friend" value="<?= $u['id'] ?>"><button class="btn-sm">添加</button></form>
                </div>
                <?php endforeach; ?>
                <?php elseif ($searchQ): ?>
                <div class="social-empty">未找到用户</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 右侧 — 聊天区 -->
    <div class="social-chat">
        <?php if ($partner): ?>
        <div class="chat-header">
            <span class="chat-header-avatar">
                <?php if (!empty($partner['avatar'])): ?>
                <img src="<?= str_starts_with($partner['avatar'],'data:') ? htmlspecialchars($partner['avatar']) : '/myweb/uploads/'.htmlspecialchars(basename($partner['avatar'])) ?>" alt="">
                <?php else: ?>
                <?= mb_strtoupper(mb_substr($partner['display_name'],0,1)) ?>
                <?php endif; ?>
            </span>
            <div>
                <div class="chat-header-name"><?= htmlspecialchars($partner['display_name']) ?></div>
                <div class="chat-header-user">@<?= htmlspecialchars($partner['username']) ?></div>
            </div>
            <form method="post" style="margin-left:auto">
                <?= csrfField() ?><input type="hidden" name="remove" value="<?= $partnerId ?>">
                <button class="btn-sm btn-danger" onclick="return confirm('确定删除该好友？')">删除好友</button>
            </form>
        </div>
        <div class="chat-body" id="chatBody">
            <?php if (empty($messages)): ?>
            <div class="chat-empty-hint">发送第一条消息开始对话</div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
            <div class="chat-msg <?= $m['from_id']==$userId?'chat-me':'chat-other' ?>">
                <?php if (in_array(($m['type'] ?? 'text'), ['image','file']) && !empty($m['image'])): ?>
                <?php $src = str_starts_with($m['image'], 'data:') ? $m['image'] : '/myweb/uploads/' . basename($m['image']); ?>
                <?php if (($m['type'] ?? '') === 'image'): ?>
                <a href="<?= htmlspecialchars($src) ?>" target="_blank" class="chat-msg-img">
                    <img src="<?= htmlspecialchars($src) ?>" alt="" loading="lazy">
                </a>
                <?php else: ?>
                <a href="<?= htmlspecialchars($src) ?>" download="<?= htmlspecialchars($m['content']) ?>" class="chat-msg-file">
                    <span class="chat-msg-file-icon">📎</span>
                    <span class="chat-msg-file-name"><?= htmlspecialchars($m['content']) ?></span>
                    <span class="chat-msg-file-dl">下载</span>
                </a>
                <?php endif; ?>
                <?php else: ?>
                <div class="chat-msg-bubble"><?= htmlspecialchars($m['content']) ?></div>
                <?php endif; ?>
                <div class="chat-msg-time"><?= date('m-d H:i', strtotime($m['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <form method="post" class="chat-input-bar" enctype="multipart/form-data" id="chatForm">
            <?= csrfField() ?>
            <input type="hidden" name="to_id" value="<?= $partnerId ?>">
            <input type="file" name="image" id="imageInput" style="display:none" onchange="document.getElementById('chatForm').submit()">
            <textarea name="content" placeholder="输入消息... Enter 发送" autofocus autocomplete="off" id="chatInput" rows="3"></textarea>
            <button type="button" class="btn-sm" onclick="document.getElementById('imageInput').click()" title="发送文件">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </button>
            <button type="submit" class="btn btn-primary">发送</button>
        </form>
        <?php else: ?>
        <div class="chat-empty">
            <div class="chat-empty-icon">💬</div>
            <h3>选择一个好友开始对话</h3>
            <p>或去「添加」搜索新朋友</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.social-full { display: grid; grid-template-columns: 320px 1fr; height: calc(100vh - var(--topbar-h)); margin: 0; overflow: hidden; }
.social-sidebar { background: var(--bg-card); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
.social-sidebar-hd { padding: var(--sp-4) var(--sp-4) 0; flex-shrink: 0; }
.social-sidebar-hd h3 { font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--sp-3); }
.social-tabs { display: flex; gap: 2px; margin-bottom: var(--sp-2); }
.social-tab { padding: var(--sp-1) var(--sp-3); font-size: 0.82rem; color: var(--text-muted); text-decoration: none; border-radius: var(--radius-sm); transition: all var(--duration-fast); position: relative; }
.social-tab:hover { color: var(--text-primary); background: var(--bg-hover); }
.social-tab.active { color: var(--accent-light); background: var(--accent-subtle); font-weight: 500; }
.social-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--danger); margin-left: 2px; vertical-align: middle; }
.social-sidebar-list { flex: 1; overflow-y: auto; padding: var(--sp-2) 0; }
.social-contact {
  display: flex; align-items: center; gap: var(--sp-3); padding: var(--sp-2) var(--sp-4);
  text-decoration: none; color: var(--text-primary); transition: background var(--duration-fast);
}
.social-contact:hover { background: var(--bg-hover); }
.social-contact.active { background: var(--accent-subtle); }
.social-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #a78bfa); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 600; flex-shrink: 0; overflow: hidden; }
.social-avatar img { width: 100%; height: 100%; object-fit: cover; }
.social-contact-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.social-contact-name { font-size: 0.9rem; font-weight: 500; }
.social-contact-user { font-size: 0.72rem; color: var(--text-muted); }
.social-empty { padding: var(--sp-6) var(--sp-4); text-align: center; color: var(--text-muted); font-size: 0.85rem; }
.social-request-card { cursor: pointer; border-bottom: 1px solid var(--border); transition: background var(--duration-fast); }
.social-request-card:hover { background: var(--bg-hover); }
.social-request-summary { display: flex; align-items: center; gap: var(--sp-3); padding: var(--sp-3) var(--sp-4); }
.social-request-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.social-request-name { font-size: 0.9rem; font-weight: 500; }
.social-request-user { font-size: 0.75rem; color: var(--text-muted); }
.social-request-actions { display: flex; gap: 4px; flex-shrink: 0; }
.social-request-detail { display: none; padding: 0 var(--sp-4) var(--sp-4) 56px; }
.social-request-card.expanded .social-request-detail { display: block; }
.social-request-bio { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: var(--sp-2); }
.social-request-meta { display: flex; gap: var(--sp-4); font-size: 0.75rem; color: var(--text-muted); }
.social-search-form { padding: var(--sp-2) var(--sp-4); }
.social-search-form input { width: 100%; }

/* 聊天区 */
.social-chat { display: flex; flex-direction: column; background: var(--gray-950); overflow: hidden; }
.chat-header { display: flex; align-items: center; gap: var(--sp-3); padding: var(--sp-3) var(--sp-5); background: var(--bg-card); border-bottom: 1px solid var(--border); flex-shrink: 0; }
.chat-header-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #a78bfa); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 600; overflow: hidden; }
.chat-header-avatar img { width: 100%; height: 100%; object-fit: cover; }
.chat-header-name { font-weight: 600; font-size: var(--text-base); }
.chat-header-user { font-size: 0.78rem; color: var(--text-muted); }
.chat-body { flex: 1; overflow-y: auto; padding: var(--sp-4) var(--sp-5); display: flex; flex-direction: column; gap: var(--sp-3); }
.chat-empty-hint { text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: var(--sp-8); }
.chat-msg { max-width: 60%; display: flex; flex-direction: column; }
.chat-me { align-self: flex-end; align-items: flex-end; }
.chat-other { align-self: flex-start; align-items: flex-start; }
.chat-msg-bubble { padding: var(--sp-2) var(--sp-4); border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.5; word-break: break-word; }
.chat-me .chat-msg-bubble { background: var(--accent); color: #fff; border-bottom-right-radius: 4px; }
.chat-other .chat-msg-bubble { background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.chat-msg-time { font-size: 0.68rem; color: var(--text-muted); margin-top: 2px; padding: 0 4px; }
.chat-msg-img { display: block; max-width: 260px; border-radius: var(--radius-md); overflow: hidden; }
.chat-msg-img img { width: 100%; display: block; }
.chat-me .chat-msg-img { border-bottom-right-radius: 4px; }
.chat-other .chat-msg-img { border-bottom-left-radius: 4px; border: 1px solid var(--border); }
.chat-msg-file { display: flex; align-items: center; gap: var(--sp-3); padding: var(--sp-3) var(--sp-4); background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); text-decoration: none; max-width: 300px; }
.chat-me .chat-msg-file { border-bottom-right-radius: 4px; background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.2); }
.chat-other .chat-msg-file { border-bottom-left-radius: 4px; }
.chat-msg-file-icon { font-size: 1.5rem; flex-shrink: 0; }
.chat-msg-file-name { flex: 1; font-size: 0.85rem; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-msg-file-dl { font-size: 0.75rem; color: var(--accent-light); font-weight: 500; white-space: nowrap; }
.chat-input-bar { display: flex; gap: var(--sp-2); padding: var(--sp-3) var(--sp-4); border-top: 1px solid var(--border); background: var(--bg-card); flex-shrink: 0; align-items: flex-end; }
.chat-input-bar textarea { flex: 1; padding: var(--sp-2) var(--sp-3); border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--bg-elevated); color: var(--text-primary); font-family: var(--font-sans); font-size: 0.9rem; line-height: 1.5; resize: none; outline: none; min-height: 44px; }
.chat-input-bar textarea:focus { border-color: var(--accent); }
.chat-input-bar .btn { flex-shrink: 0; height: 44px; }
.chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); }
.chat-empty-icon { font-size: 3.5rem; opacity: 0.15; margin-bottom: var(--sp-3); }
.chat-empty h3 { font-size: var(--text-lg); font-weight: 600; color: var(--text-primary); margin-bottom: var(--sp-1); }
.chat-empty p { font-size: 0.9rem; }
</style>

<?php if ($partnerId): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('chatBody');if(b)b.scrollTop=b.scrollHeight;});
document.getElementById('chatInput').addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();this.form.submit();}});
setInterval(function(){location.reload();},30000);
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
