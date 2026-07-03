<?php
$pageTitle = '消息';
require_once 'includes/header.php';
require_once __DIR__ . '/includes/social_service.php';

if (!isLoggedIn()) redirect('/myweb/login.php?redirect=/myweb/messages.php');

$userId = (int) $_SESSION['user_id'];
$partnerId = (int)($_GET['u'] ?? 0);

// 发送消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    verifyCsrf();
    $toId = (int)($_POST['to_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if ($toId && $content) {
        MessageService::send($userId, $toId, $content);
    }
    // 重定向避免重复提交
    redirect('/myweb/messages.php?u=' . $toId);
}

$conversations = MessageService::getConversations($userId);
$unreadCount   = MessageService::getUnreadCount($userId);
$messages      = [];
$partner        = null;

if ($partnerId) {
    $messages = MessageService::getConversation($userId, $partnerId);
    $messages = array_reverse($messages); // 按时间正序
    $partner = db()->prepare("SELECT id, username, COALESCE((SELECT display_name FROM user_settings WHERE user_id=users.id), username) AS display_name FROM users WHERE id=?")->execute([$partnerId])->fetch();
}
?>

<div class="msg-layout">
    <!-- 对话列表 -->
    <div class="msg-sidebar">
        <div class="msg-sidebar-header">
            <h3>消息 <?php if ($unreadCount): ?><span class="msg-badge"><?= $unreadCount ?></span><?php endif; ?></h3>
        </div>
        <?php if (empty($conversations)): ?>
            <p style="padding:var(--sp-4);color:var(--text-muted);font-size:0.85rem">暂无消息</p>
        <?php else: ?>
        <?php foreach ($conversations as $c): ?>
        <a href="/myweb/messages.php?u=<?= $c['id'] ?>" class="msg-contact <?= $partnerId === (int)$c['id'] ? 'active' : '' ?> <?= !$c['is_read'] && $c['from_id'] != $userId ? 'unread' : '' ?>">
            <span class="msg-contact-avatar">
                <?= mb_strtoupper(mb_substr($c['display_name'], 0, 1)) ?>
            </span>
            <div class="msg-contact-info">
                <span class="msg-contact-name"><?= htmlspecialchars($c['display_name']) ?></span>
                <span class="msg-contact-preview"><?= htmlspecialchars(mb_substr($c['last_msg'], 0, 30)) ?></span>
            </div>
            <span class="msg-contact-time"><?= date('m-d', strtotime($c['last_time'])) ?></span>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
        <div style="padding:var(--sp-3)">
            <a href="/myweb/friends.php" class="btn-sm">← 好友列表</a>
        </div>
    </div>

    <!-- 对话区 -->
    <div class="msg-main">
        <?php if ($partner): ?>
        <div class="msg-header">
            <span><?= htmlspecialchars($partner['display_name']) ?></span>
        </div>
        <div class="msg-body" id="msgBody">
            <?php foreach ($messages as $m): ?>
            <div class="msg-bubble <?= $m['from_id'] == $userId ? 'msg-me' : 'msg-other' ?>">
                <div class="msg-bubble-content"><?= htmlspecialchars($m['content']) ?></div>
                <div class="msg-bubble-time"><?= date('H:i', strtotime($m['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <form method="post" class="msg-input-bar">
            <?= csrfField() ?>
            <input type="hidden" name="to_id" value="<?= $partnerId ?>">
            <input type="text" name="content" placeholder="输入消息..." autofocus autocomplete="off">
            <button type="submit" class="btn btn-primary">发送</button>
        </form>
        <?php else: ?>
        <div class="msg-empty">
            <div style="font-size:3rem;opacity:0.2;margin-bottom:var(--sp-3)">💬</div>
            <p>选择一个好友开始对话</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.msg-layout { display: grid; grid-template-columns: 280px 1fr; max-width: var(--content-max); margin: 0 auto; height: calc(100vh - var(--topbar-h) - 2px); overflow: hidden; }
.msg-sidebar { border-right: 1px solid var(--border); background: var(--bg-card); display: flex; flex-direction: column; overflow-y: auto; }
.msg-sidebar-header { padding: var(--sp-4) var(--sp-4) var(--sp-2); }
.msg-sidebar-header h3 { font-size: var(--text-lg); font-weight: 700; display: flex; align-items: center; gap: var(--sp-2); }
.msg-badge { background: var(--accent); color: #fff; font-size: 0.7rem; padding: 1px 7px; border-radius: var(--radius-full); font-weight: 600; }
.msg-contact {
  display: flex; align-items: center; gap: var(--sp-3); padding: var(--sp-3) var(--sp-4);
  text-decoration: none; color: var(--text-primary); transition: background var(--duration-fast);
  border-left: 2px solid transparent;
}
.msg-contact:hover { background: var(--bg-hover); }
.msg-contact.active { background: var(--bg-hover); border-left-color: var(--accent); }
.msg-contact.unread .msg-contact-name { font-weight: 700; }
.msg-contact-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 600; flex-shrink: 0; }
.msg-contact-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.msg-contact-name { font-size: 0.88rem; }
.msg-contact-preview { font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-contact-time { font-size: 0.7rem; color: var(--text-muted); flex-shrink: 0; }

.msg-main { display: flex; flex-direction: column; background: var(--gray-950); }
.msg-header { padding: var(--sp-3) var(--sp-5); border-bottom: 1px solid var(--border); font-weight: 600; font-size: var(--text-base); background: var(--bg-card); }
.msg-body { flex: 1; overflow-y: auto; padding: var(--sp-4); display: flex; flex-direction: column; gap: var(--sp-3); }
.msg-bubble { max-width: 65%; display: flex; flex-direction: column; }
.msg-me { align-self: flex-end; align-items: flex-end; }
.msg-other { align-self: flex-start; align-items: flex-start; }
.msg-bubble-content { padding: var(--sp-2) var(--sp-4); border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.5; word-break: break-word; }
.msg-me .msg-bubble-content { background: var(--accent); color: #fff; border-bottom-right-radius: 4px; }
.msg-other .msg-bubble-content { background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.msg-bubble-time { font-size: 0.68rem; color: var(--text-muted); margin-top: 2px; padding: 0 4px; }
.msg-input-bar { display: flex; gap: var(--sp-2); padding: var(--sp-3) var(--sp-4); border-top: 1px solid var(--border); background: var(--bg-card); }
.msg-input-bar input { flex: 1; }
.msg-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); }
</style>

<?php if ($partnerId): ?>
<script>
// 滚动到底部
document.addEventListener('DOMContentLoaded', function() {
    var body = document.getElementById('msgBody');
    if (body) body.scrollTop = body.scrollHeight;
});
// 30秒自动刷新
setInterval(function() { location.reload(); }, 30000);
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
