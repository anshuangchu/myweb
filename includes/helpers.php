<?php
/**
 * 安全跳转（解决 header already sent 问题）
 * 优先使用 header()，失败则用 meta refresh 兜底
 */
function safeRedirect(string $url): void {
    // 仅允许站内跳转，防 open redirect
    if (!str_starts_with($url, '/') && !str_starts_with($url, 'http')) {
        $url = '/myweb/';
    }
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></head><body></body></html>';
    exit;
}

/**
 * 扫描 uploads/ 目录，返回文件列表（按修改时间降序）
 * @param array $allowed 允许的扩展名列表，默认常见文档/图片/压缩包
 */
function getUploadedFiles(array $allowed = []): array
{
    if (empty($allowed)) {
        $allowed = ['jpg','jpeg','png','gif','webp','ico','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','7z','tar','gz','mp3','mp4','mov','avi'];
    }
    $uploadDir = __DIR__ . '/../uploads/';
    $files = [];
    if (!is_dir($uploadDir)) return $files;
    $iterator = new DirectoryIterator($uploadDir);
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, $allowed)) {
                $files[] = [
                    'name'     => $file->getFilename(),
                    'size'     => $file->getSize(),
                    'time'     => $file->getMTime(),
                    'ext'      => $ext,
                    'is_image' => in_array($ext, ['jpg','jpeg','png','gif','webp','ico']),
                ];
            }
        }
    }
    usort($files, fn($a, $b) => $b['time'] - $a['time']);
    return $files;
}

function formatSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

/**
 * 渲染分页 HTML
 * @param int    $currentPage  当前页码
 * @param int    $totalPages   总页数
 * @param string $url          URL 模板（用 %d 占位页码），默认从当前 $_GET 构造
 */
function renderPagination($currentPage, $totalPages, $url = ''): string
{
    $currentPage = (int) $currentPage;
    $totalPages = (int) $totalPages;
    if ($totalPages <= 1) return '';

    if (!$url) {
        $params = $_GET;
        $params['page'] = '%d';
        $url = '?' . http_build_query($params);
    }

    $html = '<div class="pagination">';

    if ($currentPage > 1) {
        $prevUrl = str_replace('%d', (string)($currentPage - 1), $url);
        $html .= '<a href="' . htmlspecialchars($prevUrl) . '" class="page-link">← 上一页</a>';
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === $currentPage) {
            $html .= '<span class="page-link current">' . $i . '</span>';
        } elseif ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= 2) {
            $pageUrl = str_replace('%d', (string)$i, $url);
            $html .= '<a href="' . htmlspecialchars($pageUrl) . '" class="page-link">' . $i . '</a>';
        } elseif (abs($i - $currentPage) === 3) {
            $html .= '<span class="page-dots">⋯</span>';
        }
    }

    if ($currentPage < $totalPages) {
        $nextUrl = str_replace('%d', (string)($currentPage + 1), $url);
        $html .= '<a href="' . htmlspecialchars($nextUrl) . '" class="page-link">下一页 →</a>';
    }

    $html .= '</div>';
    return $html;
}
