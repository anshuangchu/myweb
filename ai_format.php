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

严格参照以下排版规范（技术文章风格）：

## 标题层级
1. 一级章节标题用 <h2>，格式为中文数字编号："一、标题"、"二、标题"、"三、标题"
2. 二级子标题用 <h3>，格式为数字编号："2.1 标题"、"3.2 标题"
3. 三级子标题用 <h4>，例如："方式一：xxx"、"步骤一：xxx"
4. 标题不要包含"第"字，直接用"一、"而非"第一章"

## 段落与文本
5. 每个段落用 <p> 包裹
6. 关键词、术语、产品名用 <strong> 加粗
7. 命令行、文件名、代码片段、配置项用 <code> 包裹
8. 段落间保持合理间距

## 代码块
9. 连续的终端命令（npm、git、curl、brew、npx、ssh 等开头）用 <pre><code> 包裹
10. 配置代码、代码示例也用 <pre><code>
11. 多行命令可合并到一个代码块

## 列表
12. 无序列表用 <ul><li>，每个 <li> 中的关键术语用 <strong>
13. 有序步骤用 <ol><li>
14. 列表项简洁，每项一行

## 表格
15. 对比/参数/特性等结构化数据用 <table>，必须有 <thead><tr><th>
16. 表格简洁清晰，最多 5-6 列

## 引用与链接
17. 参考资料、来源、GitHub 链接等放在 <blockquote><p> 中
18. 外部链接加 target="_blank"

## 其他规则
19. 不要添加文章标题（<h1>），标题已在页面单独显示
20. 不要改变文章原意，只优化格式
21. 返回完整的 HTML 片段，不要 <!DOCTYPE> 不要 <html> <body> 标签
22. 不要加任何解释文字，直接输出 HTML

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
