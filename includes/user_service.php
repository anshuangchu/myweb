<?php
/**
 * UserService — 用户设置服务
 */
class UserService
{
    /** 可用的主题预设 */
    const THEME_PRESETS = [
        '#3b82f6' => '海洋蓝',
        '#10b981' => '翡翠绿',
        '#f59e0b' => '琥珀金',
        '#f43f5e' => '玫瑰红',
        '#8b5cf6' => '紫晶',
        '#fafafa' => '极简白',
    ];

    /**
     * 获取用户设置（不存在时创建默认记录）
     */
    public static function getSettings(int $userId): array
    {
        $stmt = db()->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            self::createDefaults($userId);
            return self::getDefaults();
        }
        return $row;
    }

    /**
     * 更新用户设置
     */
    public static function updateSettings(int $userId, array $data): void
    {
        // 确保记录存在
        self::getSettings($userId);

        $fields = [];
        $params = ['user_id' => $userId];

        $allowed = ['display_name', 'bio', 'avatar', 'theme_color', 'homepage_sort', 'homepage_perpage', 'default_status'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = :$key";
                $params[$key] = $data[$key];
            }
        }

        if (empty($fields)) return;

        $sql = "UPDATE user_settings SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
        db()->prepare($sql)->execute($params);
    }

    /**
     * 获取用户主题色（用于 CSS 变量注入）
     */
    public static function getThemeColor(int $userId): string
    {
        $s = self::getSettings($userId);
        return $s['theme_color'] ?? '#3b82f6';
    }

    /**
     * 获取用户显示名
     */
    public static function getDisplayName(int $userId, string $username): string
    {
        $s = self::getSettings($userId);
        return $s['display_name'] ?: $username;
    }

    /**
     * 上传头像
     */
    public static function uploadAvatar(int $userId, array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 2 * 1024 * 1024) return null;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return null;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'])) return null;

        // base64 存储，不落盘
        $data = file_get_contents($file['tmp_name']);
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
     * 修改密码
     */
    public static function changePassword(int $userId, string $oldPassword, string $newPassword): string
    {
        $stmt = db()->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return '当前密码不正确';
        }
        if (strlen($newPassword) < 8) {
            return '新密码至少 8 个字符';
        }
        if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return '新密码必须包含字母和数字';
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        return '';
    }

    private static function createDefaults(int $userId): void
    {
        db()->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$userId]);
    }

    private static function getDefaults(): array
    {
        return [
            'user_id'          => 0,
            'display_name'     => '',
            'bio'              => '',
            'avatar'            => '',
            'theme_color'      => '#3b82f6',
            'homepage_sort'    => 'date',
            'homepage_perpage' => 10,
            'default_status'   => 'draft',
        ];
    }
}
