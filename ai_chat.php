<?php
/**
 * AI 对话助手 — AJAX 端点
 *
 * POST /ai_chat.php
 * 参数: question - 用户问题
 *
 * 流程: 提取关键词 → 搜索相关文章/资料 → 拼接上下文 → AI 回答
 *
 * 返回: JSON { success: bool, data?: {answer:string, sources:int}, error?: string }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db_loader.php';
ini_set('session.gc_maxlifetime', 3600);
if (session_status() === PHP_SESSION_NONE) session_start();

// 限流：每小时每 IP 20 次
$ip = getClientIP();
$rateKey = 'chat_rate_' . md5($ip);
$rateCount = $_SESSION[$rateKey]['count'] ?? 0;
$rateTime = $_SESSION[$rateKey]['time'] ?? 0;
if ($rateCount >= 20 && time() - $rateTime < 3600) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => '对话频率过高，请稍后再试']);
    exit;
}
// 重置计数器（超过 1 小时）
if (time() - $rateTime > 3600) {
    $rateCount = 0;
    $rateTime = time();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '仅支持 POST']);
    exit;
}

// CSRF 验证
verifyCsrf();

$question = trim($_POST['question'] ?? '');
if (!$question) {
    echo json_encode(['success' => false, 'error' => '请输入问题']);
    exit;
}

try {
    require_once __DIR__ . '/includes/ai_service.php';
    $ai = new AiService();

    // 1. 提取关键词
    $keywords = $ai->expandSearchQuery($question);
    // 取前 3 个关键词搜索
    $searchTerms = array_slice($keywords, 0, 3);

    // 2. 搜索相关文章
    $contextPieces = [];
    $articleCount = 0;

    foreach ($searchTerms as $kw) {
        $keyword = '%' . $kw . '%';
        $stmt = db()->prepare("SELECT title, summary FROM articles WHERE status='published' AND (title LIKE ? OR summary LIKE ?) LIMIT 5");
        $stmt->execute([$keyword, $keyword]);
        foreach ($stmt->fetchAll() as $row) {
            $summary = $row['summary'] ?: '';
            if ($summary) {
                $contextPieces[] = "[文章] {$row['title']}: {$summary}";
                $articleCount++;
            }
        }
        if ($articleCount >= 8) break;
    }

    // 如果文章不足，再搜索资料页面
    if ($articleCount < 3) {
        foreach ($searchTerms as $kw) {
            $keyword = '%' . $kw . '%';
            $stmt = db()->prepare("SELECT title, content FROM pages WHERE status='published' AND (title LIKE ? OR content LIKE ?) LIMIT 3");
            $stmt->execute([$keyword, $keyword]);
            foreach ($stmt->fetchAll() as $row) {
                $summary = mb_substr(strip_tags($row['content']), 0, 200);
                $contextPieces[] = "[资料] {$row['title']}: {$summary}";
                $articleCount++;
            }
            if ($articleCount >= 5) break;
        }
    }

    // 3. 拼接上下文
    $context = empty($contextPieces)
        ? "网站目前没有与「{$question}」直接相关的内容。请基于通用知识回答用户问题。"
        : "以下是本站与问题相关的内容：\n" . implode("\n---\n", array_slice($contextPieces, 0, 10));

    // 4. AI 回答
    $answer = $ai->chatWithContext($question, $context);

    // 5. 更新限流计数器
    $_SESSION[$rateKey] = ['count' => $rateCount + 1, 'time' => $rateTime];

    echo json_encode([
        'success' => true,
        'data'    => [
            'answer'  => $answer,
            'sources' => $articleCount,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('AI Chat error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'AI 服务暂时不可用，请稍后再试']);
}
