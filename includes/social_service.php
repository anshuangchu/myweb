<?php
/**
 * FriendService — 好友系统
 */
class FriendService
{
    public static function sendRequest(int $fromId, int $toId): string
    {
        if ($fromId === $toId) return '不能添加自己';
        $db = db();
        $stmt = $db->prepare("SELECT status FROM friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)");
        $stmt->execute([$fromId, $toId, $toId, $fromId]);
        $row = $stmt->fetch();
        if ($row) {
            if ($row['status'] === 'accepted') return '已经是好友';
            if ($row['status'] === 'pending') return '已发送过请求，等待对方通过';
        }
        $db->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?,?, 'pending')")->execute([$fromId, $toId]);
        return '';
    }

    public static function accept(int $friendId, int $userId): void
    {
        db()->prepare("UPDATE friends SET status='accepted', updated_at=NOW() WHERE user_id=? AND friend_id=? AND status='pending'")->execute([$friendId, $userId]);
    }

    public static function remove(int $userId, int $friendId): void
    {
        db()->prepare("DELETE FROM friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)")->execute([$userId, $friendId, $friendId, $userId]);
    }

    public static function getFriends(int $userId): array
    {
        $stmt = db()->prepare(
            "SELECT u.id, u.username, COALESCE(s.display_name, u.username) AS display_name, s.avatar
             FROM friends f
             JOIN users u ON (f.friend_id = u.id OR f.user_id = u.id) AND u.id != ?
             LEFT JOIN user_settings s ON s.user_id = u.id
             WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted'
             ORDER BY s.display_name, u.username"
        );
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public static function getPendingRequests(int $userId): array
    {
        $stmt = db()->prepare(
            "SELECT f.id, f.created_at, u.id AS user_id, u.username, u.email,
                    COALESCE(s.display_name, u.username) AS display_name,
                    s.bio, s.avatar
             FROM friends f JOIN users u ON f.user_id = u.id
             LEFT JOIN user_settings s ON s.user_id = u.id
             WHERE f.friend_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getSentCount(int $userId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM friends WHERE user_id=? AND status='pending'");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function searchUsers(string $q, int $excludeId): array
    {
        $kw = '%' . $q . '%';
        $stmt = db()->prepare(
            "SELECT id, username, COALESCE((SELECT display_name FROM user_settings WHERE user_id=users.id), username) AS display_name
             FROM users WHERE id != ? AND (username LIKE ? OR email LIKE ?) AND status = 1 LIMIT 20"
        );
        $stmt->execute([$excludeId, $kw, $kw]);
        return $stmt->fetchAll();
    }
}

class MessageService
{
    const RETENTION_DAYS = 30;

    public static function cleanup(): void
    {
        db()->prepare("DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([self::RETENTION_DAYS]);
    }

    public static function send(int $fromId, int $toId, string $content): void
    {
        self::cleanup();
        db()->prepare("INSERT INTO messages (from_id, to_id, content, type) VALUES (?,?,?,'text')")->execute([$fromId, $toId, trim($content)]);
    }

    /**
     * 发送附件（图片或文件）— base64 存储，不落盘
     * @return ?string 错误消息，成功返回 null
     */
    public static function sendAttachment(int $fromId, int $toId, array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return '上传失败';
        if ($file['size'] > 5 * 1024 * 1024) return '文件不能超过 5MB';

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = $file['name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $data = file_get_contents($file['tmp_name']);
        $b64  = 'data:' . $mime . ';base64,' . base64_encode($data);

        $isImage = str_starts_with($mime, 'image/');
        $type    = $isImage ? 'image' : 'file';

        self::cleanup();
        db()->prepare("INSERT INTO messages (from_id, to_id, content, type, image) VALUES (?,?,?,?,?)")
           ->execute([$fromId, $toId, $name, $type, $b64]);
        return null;
    }

    public static function getConversation(int $userId, int $partnerId, int $limit = 50): array
    {
        self::cleanup();
        db()->prepare("UPDATE messages SET is_read=1 WHERE from_id=? AND to_id=? AND is_read=0")->execute([$partnerId, $userId]);

        $stmt = db()->prepare(
            "SELECT m.*, u.username AS from_name FROM messages m
             JOIN users u ON m.from_id = u.id
             WHERE (m.from_id=? AND m.to_id=?) OR (m.from_id=? AND m.to_id=?)
             ORDER BY m.created_at DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$userId, $partnerId, $partnerId, $userId]);
        return $stmt->fetchAll();
    }

    public static function getConversations(int $userId): array
    {
        self::cleanup();
        $stmt = db()->query(
            "SELECT u.id, u.username, COALESCE(s.display_name, u.username) AS display_name, s.avatar,
                    m.content AS last_msg, m.created_at AS last_time, m.is_read, m.from_id
             FROM messages m
             JOIN users u ON (m.from_id = u.id OR m.to_id = u.id) AND u.id != $userId
             LEFT JOIN user_settings s ON s.user_id = u.id
             WHERE m.id IN (
                 SELECT MAX(id) FROM messages
                 WHERE from_id = $userId OR to_id = $userId
                 GROUP BY CASE WHEN from_id = $userId THEN to_id ELSE from_id END
             )
             ORDER BY m.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public static function getUnreadCount(int $userId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM messages WHERE to_id=? AND is_read=0");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
