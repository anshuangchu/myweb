<?php
/**
 * ArticleStatus — 文章状态常量、状态机、验证函数
 *
 * 状态流转:
 *   draft → pending → published → archived
 *   draft ──────────────────────→ archived (跳过审核)
 *   published ←── draft (拒绝后回到草稿)
 */

class ArticleStatus
{
    const DRAFT     = 'draft';
    const PENDING   = 'pending';
    const PUBLISHED = 'published';
    const ARCHIVED  = 'archived';

    const ALL = [self::DRAFT, self::PENDING, self::PUBLISHED, self::ARCHIVED];

    const LABELS = [
        self::DRAFT     => '草稿',
        self::PENDING   => '待审核',
        self::PUBLISHED => '已发布',
        self::ARCHIVED  => '已归档',
    ];

    /** 检查状态值是否合法 */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    /** 获取可读状态标签 */
    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    /** 获取角色可用的状态列表 */
    public static function allowedForRole(string $role): array
    {
        return in_array($role, ['super_admin', 'admin'], true)
            ? self::ALL
            : [self::DRAFT, self::PENDING, self::ARCHIVED];
    }

    /**
     * 根据角色调整状态（editor 主动选 published 时自动降为 pending）
     */
    public static function adjustForRole(string $status, string $role): string
    {
        if ($status === self::PUBLISHED && !in_array($role, ['super_admin', 'admin'], true)) {
            return self::PENDING;
        }
        if (!self::isValid($status)) {
            return self::DRAFT;
        }
        return $status;
    }
}

/**
 * 验证文章输入，返回错误消息数组（空 = 无错误）
 */
function validateArticleInput(array $data): array
{
    $errors = [];
    $title  = trim($data['title'] ?? '');

    if ($title === '') {
        $errors[] = '请输入文章标题';
    }

    $status = $data['status'] ?? 'draft';
    if (!ArticleStatus::isValid($status)) {
        $errors[] = '无效的文章状态';
    }

    return $errors;
}

/**
 * 验证封面图片上传，返回错误消息（null = 通过）
 */
function validateCoverImage(array $file): ?string
{
    if (empty($file['name'])) return null;

    if ($file['size'] > 5 * 1024 * 1024) {
        return '封面图片不能超过 5MB';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed, true) || !getimagesize($file['tmp_name'])) {
        return '仅支持 JPG/PNG/GIF/WebP 格式的图片';
    }

    return null;
}

/**
 * 处理封面图片上传，返回新文件路径
 */
function processCoverImage(array $file): string
{
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'uploads/' . uniqid() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $name);
    return $name;
}
