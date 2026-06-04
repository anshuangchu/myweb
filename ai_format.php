<?php
/**
 * AI 文章排版 — AJAX 端点
 *
 * POST /ai_format.php
 * 参数: content (原始HTML), title (可选)
 * 返回: JSON { success: bool, html?: string, error?: string }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db_loader.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 仅登录用户可用
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '仅支持 POST']);
    exit;
}

verifyCsrf();

$content = $_POST['content'] ?? '';
$title = $_POST['title'] ?? '';

if (trim(strip_tags($content)) === '') {
    echo json_encode(['success' => false, 'error' => '内容为空']);
    exit;
}

try {
    require_once __DIR__ . '/includes/ai_service.php';
    $ai = new AiService();

    $prompt = <<<PROMPT
你是一个专业的文章排版助手。请将以下文章内容格式化为美观的HTML。

规则：
1. 识别代码块：连续的命令行（如 npm/git/curl/export/set/ssh 等开头）包裹在 <pre><code> 中
2. 识别标题：如"第一阶段："、"1.1 xxx"等作为 <h3>
3. 识别子标题："1.1 xxx"、"方式一："等作为 <h4>
4. 识别警告："⚠️" 或 "⚠" 开头的内容用 <p class="article-warn">
5. 保持原有段落结构，适当调整间距
6. 不要改变文章原意，只优化格式
7. 返回完整的HTML片段，不要加任何解释

文章标题：$title

文章内容：
$content
PROMPT;

    $formatted = $ai->chat([
        ['role' => 'system', 'content' => '你是一个文章排版助手，只返回格式化的HTML，不返回任何解释或额外文字。'],
        ['role' => 'user', 'content' => $prompt],
    ], 4096, 0.1);

    echo json_encode(['success' => true, 'html' => $formatted], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'AI 服务异常：' . $e->getMessage()]);
}
