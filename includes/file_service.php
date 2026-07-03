<?php
/**
 * FileService — 统一文件操作服务
 *
 * 封装文件扫描、图标映射、安全文件名生成
 * 替代 helpers.php 中的 getUploadedFiles()（保留旧函数兼容性）
 */

class FileService
{
    /**
     * 文件类型分类定义（含扩展名列表 + 图标 + 标签）
     */
    private const CATEGORIES = [
        'image'    => ['label' => '图片',   'icon' => '🖼️', 'exts' => ['jpg','jpeg','png','gif','webp','ico']],
        'document' => ['label' => '文档',   'icon' => '📄', 'exts' => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt']],
        'archive'  => ['label' => '压缩包', 'icon' => '📦', 'exts' => ['zip','rar','7z','tar','gz']],
        'media'    => ['label' => '音视频', 'icon' => '🎬', 'exts' => ['mp3','mp4','mov','avi']],
    ];

    /**
     * 统一的扩展名 → emoji 图标映射（唯一权威来源）
     */
    private const ICON_MAP = [
        'pdf'  => '📄', 'doc'  => '📝', 'docx' => '📝',
        'xls'  => '📊', 'xlsx' => '📊', 'ppt'  => '📽️', 'pptx' => '📽️',
        'txt'  => '📃', 'zip'  => '📦', 'rar'  => '📦', '7z'   => '📦',
        'tar'  => '📦', 'gz'   => '📦', 'mp3'  => '🎵', 'mp4'  => '🎬',
        'mov'  => '🎬', 'avi'  => '🎬',
    ];

    /**
     * 允许的扩展名（上传白名单）
     */
    private const ALLOWED_EXTS = [
        'jpg','jpeg','png','gif','webp','ico',
        'pdf','doc','docx','xls','xlsx','ppt','pptx','txt',
        'zip','rar','7z','tar','gz',
        'mp3','mp4','mov','avi',
    ];

    // ================================================================
    //  公共方法
    // ================================================================

    /**
     * 获取文件列表 + 分类计数（一次目录扫描）
     *
     * @param string $typeFilter  分类 key（'all'|'image'|'document'|'archive'|'media'）
     * @param string $searchQuery 文件名搜索关键词
     * @return array{files: array, categories: array, stats: array}
     */
    public static function getFiles(string $typeFilter = 'all', string $searchQuery = ''): array
    {
        $uploadDir = __DIR__ . '/../uploads/';
        $allFiles = [];

        if (!is_dir($uploadDir)) {
            return ['files' => [], 'categories' => [], 'stats' => ['total' => 0, 'totalSize' => 0]];
        }

        $allowedExts = self::getAllowedExts($typeFilter);
        $iterator = new DirectoryIterator($uploadDir);

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if ($allowedExts !== null && !in_array($ext, $allowedExts, true)) continue;

            $name = $file->getFilename();
            // 过滤系统文件
            if (str_starts_with($name, 'avatar_') || str_starts_with($name, 'bg-') || str_starts_with($name, 'msg_')) continue;
            // 文件名搜索过滤
            if ($searchQuery && mb_stripos($name, $searchQuery) === false) continue;

            $allFiles[] = [
                'name'     => $name,
                'size'     => $file->getSize(),
                'time'     => $file->getMTime(),
                'ext'      => $ext,
                'is_image' => in_array($ext, ['jpg','jpeg','png','gif','webp','ico'], true),
                'icon'     => self::getFileIcon($ext, true),
                'category' => self::getCategory($ext),
            ];
        }

        // 按修改时间降序
        usort($allFiles, fn($a, $b) => $b['time'] <=> $a['time']);

        // 基于全量文件（不考虑 typeFilter）计算所有分类计数
        $allForCounts = $allFiles;
        if ($typeFilter !== 'all' || $searchQuery) {
            // 如果做了筛选，需要重新扫描全量文件来计数
            if ($typeFilter !== 'all') {
                $allForCounts = self::scanDir($uploadDir, null, '');
            }
        }

        $categories = [];
        $categories['all'] = ['label' => '全部', 'icon' => '📁', 'count' => count($allForCounts)];
        foreach (self::CATEGORIES as $key => $cat) {
            $categories[$key] = [
                'label' => $cat['label'],
                'icon'  => $cat['icon'],
                'count' => count(array_filter($allForCounts, fn($f) => $f['category'] === $key)),
            ];
        }

        // 如果需要分类计数但当前已做筛选，仍需重新计算各分类总数
        if ($typeFilter !== 'all' && empty($searchQuery)) {
            // typeFilter 非 all 时，allForCounts 已是全量（上面分支）
        } elseif ($typeFilter === 'all' && $searchQuery) {
            // 搜索模式下，需要各分类在搜索结果中的计数
            foreach (self::CATEGORIES as $key => $cat) {
                $categories[$key]['count'] = count(array_filter($allFiles, fn($f) => $f['category'] === $key));
            }
        }

        return [
            'files'      => $allFiles,
            'categories' => $categories,
            'stats'      => [
                'total'     => count($allFiles),
                'totalSize' => array_sum(array_column($allFiles, 'size')),
            ],
        ];
    }

    /**
     * 获取文件 emoji 图标
     *
     * @param string $ext      扩展名（小写）
     * @param bool   $isImage  是否已知为图片
     */
    public static function getFileIcon(string $ext, bool $isImage = false): string
    {
        if ($isImage) return '🖼️';
        return self::ICON_MAP[$ext] ?? '📎';
    }

    /**
     * 生成安全的唯一文件名（防碰撞）
     *
     * @param string $originalName 原始文件名
     * @param string $ext          扩展名
     */
    public static function generateSafeFilename(string $originalName, string $ext): string
    {
        $safeBase = preg_replace('/[^a-zA-Z0-9._\-\x{4e00}-\x{9fa5}]/u', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBase = mb_substr($safeBase, 0, 60); // 限制长度
        return bin2hex(random_bytes(16)) . '_' . $safeBase . '.' . $ext;
    }

    /**
     * 流式读取文本文件（防 OOM）
     *
     * @param string $path     文件路径
     * @param int    $maxChars 最大读取字符数
     */
    public static function readTextPreview(string $path, int $maxChars = 50000): string
    {
        $fh = fopen($path, 'rb');
        if (!$fh) return '';
        $content = fread($fh, min($maxChars * 4, filesize($path))); // 估计最多 4 字节/字符
        fclose($fh);

        if (mb_strlen($content) > $maxChars) {
            $content = mb_substr($content, 0, $maxChars) . "\n\n... (文件过大，仅显示前 " . number_format($maxChars) . " 字符)";
        }
        return $content;
    }

    /**
     * 获取允许的扩展名列表
     */
    public static function getAllowedExts(?string $typeFilter = null): ?array
    {
        if ($typeFilter === null || $typeFilter === 'all') return self::ALLOWED_EXTS;
        return self::CATEGORIES[$typeFilter]['exts'] ?? null;
    }

    /**
     * 获取分类定义
     */
    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    // ================================================================
    //  私有方法
    // ================================================================

    /**
     * 扫描目录（内部使用）
     */
    private static function scanDir(string $uploadDir, ?array $allowedExts, string $searchQuery): array
    {
        $files = [];
        if (!is_dir($uploadDir)) return $files;

        $iterator = new DirectoryIterator($uploadDir);
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if ($allowedExts !== null && !in_array($ext, $allowedExts, true)) continue;

            $name = $file->getFilename();
            // 过滤系统文件
            if (str_starts_with($name, 'avatar_') || str_starts_with($name, 'bg-') || str_starts_with($name, 'msg_')) continue;
            if ($searchQuery && mb_stripos($name, $searchQuery) === false) continue;

            $files[] = [
                'name'     => $name,
                'size'     => $file->getSize(),
                'time'     => $file->getMTime(),
                'ext'      => $ext,
                'is_image' => in_array($ext, ['jpg','jpeg','png','gif','webp','ico'], true),
                'icon'     => self::getFileIcon($ext, in_array($ext, ['jpg','jpeg','png','gif','webp','ico'], true)),
                'category' => self::getCategory($ext),
            ];
        }

        usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
        return $files;
    }

    /**
     * 重命名文件（安全：禁止路径遍历，保留扩展名一致性）
     *
     * @param string $oldName 当前文件名（basename）
     * @param string $newName 新文件名（basename，需含扩展名）
     * @return bool
     */
    public static function renameFile(string $oldName, string $newName): bool
    {
        $uploadDir = __DIR__ . '/../uploads/';
        $oldPath = $uploadDir . basename($oldName);
        $newPath = $uploadDir . basename($newName);

        if (!file_exists($oldPath) || !is_file($oldPath)) return false;
        if (file_exists($newPath)) return false; // 不覆盖已有文件
        if ($oldName === $newName) return true;

        return rename($oldPath, $newPath);
    }

    /**
     * 保存文本内容到文件（原子写入：先写临时文件再 rename）
     *
     * @param string $fileName 文件名（basename）
     * @param string $content  新内容
     * @return bool
     */
    public static function saveTextFile(string $fileName, string $content): bool
    {
        $uploadDir = __DIR__ . '/../uploads/';
        $path = $uploadDir . basename($fileName);

        if (!file_exists($path) || !is_file($path)) return false;

        $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpPath, $content) === false) return false;
        return rename($tmpPath, $path);
    }

    /**
     * 判断文件是否为可编辑文本类型
     */
    public static function isTextEditable(string $ext): bool
    {
        return in_array($ext, ['txt','md','csv','log','json','xml','html','htm','css','js','php','py','java','c','cpp','h','sh','bat','sql','yml','yaml','ini','cfg','conf','env'], true);
    }

    /**
     * 根据扩展名判断分类
     */
    private static function getCategory(string $ext): string
    {
        foreach (self::CATEGORIES as $key => $cat) {
            if (in_array($ext, $cat['exts'], true)) return $key;
        }
        return 'all';
    }
}
