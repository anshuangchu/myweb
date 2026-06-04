<?php
/**
 * AI 文章助手 — AJAX 端点
 *
 * 调用方式: POST
 * 参数:
 *   action  - summarize | polish | expand | generate | suggest_title
 *   content - 文章正文
 *   title   - 可选，文章标题
 *   style   - 可选 (generate 时有效) general | formal | casual | tech
 *   length  - 可选 (expand 时有效) short | medium | long
 *
 * 返回: JSON { success: bool, data?: string|array, error?: string }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/ai_service.php';

// 检查 API Key 是否已配置
try {
    $ai = new AiService();
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '未配置 AI API Key，请联系管理员']);
    exit;
}

// 权限校验
require_once __DIR__ . '/../includes/db_loader.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}
$stmt = db()->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'editor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '无权限']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '仅支持 POST']);
    exit;
}

$action  = $_POST['action'] ?? '';
$content = trim($_POST['content'] ?? '');
$title   = trim($_POST['title'] ?? '');
$style   = trim($_POST['style'] ?? 'general');
$length  = trim($_POST['length'] ?? 'medium');

try {
    switch ($action) {
        case 'summarize':
            if (!$content) throw new RuntimeException('文章内容不能为空');
            $data = $ai->summarize($content, $title);
            break;

        case 'polish':
            if (!$content) throw new RuntimeException('文章内容不能为空');
            $data = $ai->polish($content);
            break;

        case 'expand':
            if (!$content) throw new RuntimeException('文章内容不能为空');
            $data = $ai->expand($content, $title, $length);
            break;

        case 'generate':
            if (!$content) throw new RuntimeException('请输入文章主题');
            $data = $ai->generateArticle($content, $style);
            break;

        case 'suggest_title':
            if (!$content) throw new RuntimeException('文章内容不能为空');
            $data = $ai->suggestTitle($content);
            break;

        default:
            throw new RuntimeException('未知操作');
    }

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);

} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
