<?php
/**
 * 小米 MiMo 对话 — AJAX 端点
 *
 * 操作:
 *   POST send   — 发送消息，AI 回复，保存记录
 *   GET  list   — 获取用户对话列表
 *   GET  load   — 加载指定对话的消息 (?id=)
 *   POST new    — 创建新对话
 *   POST delete — 删除指定对话 (?id=)
 *   POST rename — 重命名对话 (?id=&title=)
 *
 * 用户识别: 已登录用 user_id，未登录用 session_id
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db_loader.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$method  = $_SERVER['REQUEST_METHOD'];

// CSRF 验证（POST 操作需要）
if ($method === 'POST') {
    verifyCsrf();
}
$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$userId  = $_SESSION['user_id'] ?? null;
$sessionId = session_id();

// ===== 辅助函数 =====

function getConvId(): int
{
    return (int)($_GET['id'] ?? $_POST['id'] ?? 0);
}

function checkOwnership(int $convId): ?array
{
    global $userId, $sessionId;
    if ($userId) {
        $stmt = db()->prepare("SELECT * FROM mimo_conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$convId, $userId]);
    } else {
        $stmt = db()->prepare("SELECT * FROM mimo_conversations WHERE id = ? AND session_id = ?");
        $stmt->execute([$convId, $sessionId]);
    }
    $conv = $stmt->fetch();
    if (!$conv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => '对话不存在']);
        exit;
    }
    return $conv;
}

// ===== 路由 =====

try {
    switch ($action) {

        // ===== 发送消息（含指令解析） =====
        case 'send':
            if ($method !== 'POST') throw new RuntimeException('Method not allowed');

            $convId   = (int)($_POST['id'] ?? 0);
            $question = trim($_POST['message'] ?? '');
            $model    = trim($_POST['model'] ?? '');

            if (!$question) throw new RuntimeException('请输入消息');

            // 没有会话 ID 则创建新对话
            if (!$convId) {
                $convId = createConversation($userId, $sessionId, mb_substr($question, 0, 30), $model);
            } else {
                $conv = checkOwnership($convId);
                if (!$model) $model = $conv['model'];
            }

            // 保存用户消息
            $stmt = db()->prepare("INSERT INTO mimo_messages (conversation_id, role, content) VALUES (?, 'user', ?)");
            $stmt->execute([$convId, $question]);
            db()->prepare("UPDATE mimo_conversations SET message_count = message_count + 1 WHERE id = ?")->execute([$convId]);

            // 加载最近 20 条消息作为上下文
            $stmt = db()->prepare("SELECT role, content FROM mimo_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 20");
            $stmt->execute([$convId]);
            $history = $stmt->fetchAll();

            require_once __DIR__ . '/includes/mimo_service.php';
            $mimo = new MimoService();

            // ===== 系统提示词 + 工具描述 =====
            $systemPrompt = '你是有用的 AI 助手，请用中文回答用户问题。';
            $systemPrompt .= "\n\n你可以通过输出 JSON 指令来操作本站网页。需要操作时，先单独输出一行 JSON 指令，再换行输出你的回复。";
            $systemPrompt .= "\n可用指令：";
            $systemPrompt .= "\n- 搜索文章: {\"tool\":\"search_articles\",\"args\":{\"keyword\":\"关键词\",\"limit\":10}}";
            $systemPrompt .= "\n- 查看文章: {\"tool\":\"get_article\",\"args\":{\"id\":123}}";
            $systemPrompt .= "\n- 浏览网页: {\"tool\":\"browse_page\",\"args\":{\"url\":\"/myweb/...\"}}";
            $systemPrompt .= "\n- 导航页面: {\"tool\":\"navigate_to\",\"args\":{\"url\":\"/myweb/...\"}}";
            $systemPrompt .= "\n- 列出文章: {\"tool\":\"list_articles\",\"args\":{\"limit\":10}}";
            $systemPrompt .= "\n\n执行结果会包含图片列表（images 字段）。你可以用 Markdown 格式显示图片: ![图片描述](图片URL)";
            $systemPrompt .= "\n不需要操作时正常回复即可，不要输出 JSON 指令。";

            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }

            // ===== 第一轮：调用 AI（不带 tools 参数） =====
            $reply = $mimo->chat($messages, $model, 4096, 0.7);

            // ===== 解析回复中的工具指令 =====
            $toolCmd = null;
            foreach (explode("\n", $reply) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $decoded = json_decode($line, true);
                if ($decoded && isset($decoded['tool'], $decoded['args']) && is_array($decoded['args'])) {
                    $toolCmd = $decoded;
                    break;
                }
            }

            if ($toolCmd) {
                // ===== 执行工具 =====
                $toolName = $toolCmd['tool'];
                $toolArgs = $toolCmd['args'];
                $navigate = null;

                try {
                    $result = executeMimoTool($toolName, $toolArgs);
                    if ($toolName === 'navigate_to' && !empty($result['url'])) {
                        $navigate = $result['url'];
                    }
                } catch (Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }

                // ===== 第二轮：带工具结果重新请求 =====
                $messages[] = ['role' => 'assistant', 'content' => $reply];
                $messages[] = ['role' => 'system', 'content' => '工具 "' . $toolName . '" 执行结果: ' . json_encode($result, JSON_UNESCAPED_UNICODE) . '。请根据结果用中文回答用户。'];

                $finalReply = $mimo->chat($messages, $model, 2048, 0.7);

                // 保存 AI 回复
                $stmt = db()->prepare("INSERT INTO mimo_messages (conversation_id, role, content) VALUES (?, 'assistant', ?)");
                $stmt->execute([$convId, $finalReply]);
                db()->prepare("UPDATE mimo_conversations SET message_count = message_count + 1, model = ? WHERE id = ?")
                    ->execute([$model ?: $mimo->getDefaultModel(), $convId]);

                echo json_encode([
                    'success' => true,
                    'data'    => [
                        'reply'    => $finalReply,
                        'conv_id'  => $convId,
                        'type'     => 'tool_result',
                        'tool'     => $toolName,
                        'navigate' => $navigate,
                    ],
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // ===== 无工具指令，直接返回 =====
                $stmt = db()->prepare("INSERT INTO mimo_messages (conversation_id, role, content) VALUES (?, 'assistant', ?)");
                $stmt->execute([$convId, $reply]);
                db()->prepare("UPDATE mimo_conversations SET message_count = message_count + 1, model = ? WHERE id = ?")
                    ->execute([$model ?: $mimo->getDefaultModel(), $convId]);

                echo json_encode([
                    'success' => true,
                    'data'    => [
                        'reply'      => $reply,
                        'conv_id'    => $convId,
                        'message_id' => (int)db()->lastInsertId(),
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        // ===== 获取对话列表 =====
        case 'list':
            if ($userId) {
                $stmt = db()->prepare("SELECT id, title, model, message_count, created_at, updated_at FROM mimo_conversations WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50");
                $stmt->execute([$userId]);
            } else {
                $stmt = db()->prepare("SELECT id, title, model, message_count, created_at, updated_at FROM mimo_conversations WHERE session_id = ? ORDER BY updated_at DESC LIMIT 50");
                $stmt->execute([$sessionId]);
            }
            $convs = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $convs], JSON_UNESCAPED_UNICODE);
            break;

        // ===== 加载对话消息 =====
        case 'load':
            $convId = getConvId();
            checkOwnership($convId);

            $stmt = db()->prepare("SELECT id, role, content, created_at FROM mimo_messages WHERE conversation_id = ? ORDER BY id ASC");
            $stmt->execute([$convId]);
            $msgs = $stmt->fetchAll();

            // 获取对话信息
            $stmt = db()->prepare("SELECT id, title, model, message_count FROM mimo_conversations WHERE id = ?");
            $stmt->execute([$convId]);
            $conv = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'data'    => [
                    'conversation' => $conv,
                    'messages'     => $msgs,
                ],
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ===== 创建新对话 =====
        case 'new':
            $title = trim($_POST['title'] ?? '新对话');
            $model = trim($_POST['model'] ?? '');
            $convId = createConversation($userId, $sessionId, $title, $model);

            echo json_encode([
                'success' => true,
                'data'    => ['id' => $convId, 'title' => $title],
            ]);
            break;

        // ===== 删除对话 =====
        case 'delete':
            $convId = getConvId();
            checkOwnership($convId);

            db()->prepare("DELETE FROM mimo_messages WHERE conversation_id = ?")->execute([$convId]);
            db()->prepare("DELETE FROM mimo_conversations WHERE id = ?")->execute([$convId]);

            echo json_encode(['success' => true]);
            break;

        // ===== 重命名对话 =====
        case 'rename':
            $convId = getConvId();
            $title  = trim($_POST['title'] ?? '');
            if (!$title) throw new RuntimeException('标题不能为空');
            checkOwnership($convId);

            db()->prepare("UPDATE mimo_conversations SET title = ? WHERE id = ?")->execute([$title, $convId]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new RuntimeException('未知操作: ' . $action);
    }

} catch (Throwable $e) {
    error_log('MiMo Chat error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '服务暂时不可用，请稍后再试']);
}

// ===== 工具执行 =====
function executeMimoTool(string $name, array $args): array
{
    switch ($name) {
        case 'browse_page':
            $url = $args['url'] ?? '';
            if (!$url) return ['error' => '缺少 URL'];
            // 严格限制：仅允许 /myweb/ 路径，防 SSRF
            if (!str_starts_with($url, '/myweb/')) {
                return ['error' => '仅允许访问站内页面'];
            }
            $fullUrl = 'http://localhost' . $url;
            $ch = curl_init($fullUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $html     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200 || !$html) {
                return ['error' => "无法访问页面: HTTP {$httpCode}"];
            }
            // 提取页面中的图片
            $images = [];
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
                foreach ($matches[1] as $src) {
                    $images[] = str_starts_with($src, 'http') ? $src : '/myweb/' . ltrim($src, '/');
                }
            }
            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = mb_substr(trim($text), 0, 5000);
            return [
                'content' => $text,
                'url'     => $url,
                'images'  => array_slice(array_values(array_unique($images)), 0, 10),
            ];

        case 'search_articles':
            $keyword = trim($args['keyword'] ?? '');
            $limit   = min((int)($args['limit'] ?? 10), 50);
            if (!$keyword) return ['error' => '请输入搜索关键词'];
            $like = "%{$keyword}%";
            $stmt = db()->prepare("SELECT a.id, a.title, a.summary, a.cover_image, a.created_at, c.name AS category FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE (a.title LIKE ? OR a.content LIKE ? OR a.summary LIKE ?) AND a.status = 'published' ORDER BY a.created_at DESC LIMIT ?");
            $stmt->execute([$like, $like, $like, $limit]);
            $articles = $stmt->fetchAll();
            return ['articles' => $articles, 'count' => count($articles)];

        case 'navigate_to':
            $url = $args['url'] ?? '';
            if (!$url) return ['error' => '缺少 URL'];
            // 严格限制：仅允许 /myweb/ 路径，防 open redirect
            if (!str_starts_with($url, '/myweb/')) {
                return ['error' => '仅支持站内导航'];
            }
            return ['url' => $url, 'message' => '正在导航到: ' . $url];

        case 'get_article':
            $id = (int)($args['id'] ?? 0);
            if (!$id) return ['error' => '缺少文章 ID'];
            $stmt = db()->prepare("SELECT a.id, a.title, a.content, a.summary, a.cover_image, a.created_at, c.name AS category FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.id = ? AND a.status = 'published'");
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            if (!$article) return ['error' => '文章不存在或未发布'];
            // 提取文章中的图片（封面 + 内容中的图片）
            $imgList = [];
            if ($article['cover_image']) {
                $imgList[] = '/myweb/' . ltrim($article['cover_image'], '/');
            }
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $article['content'], $matches)) {
                foreach ($matches[1] as $src) {
                    $imgList[] = str_starts_with($src, 'http') ? $src : '/myweb/' . ltrim($src, '/');
                }
            }
            $article['images'] = array_values(array_unique($imgList));
            return ['article' => $article];

        case 'list_articles':
            $limit    = min((int)($args['limit'] ?? 10), 50);
            $category = trim($args['category'] ?? '');
            if ($category) {
                $stmt = db()->prepare("SELECT a.id, a.title, a.summary, a.cover_image, a.created_at, c.name AS category FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.status = 'published' AND c.name = ? ORDER BY a.created_at DESC LIMIT ?");
                $stmt->execute([$category, $limit]);
            } else {
                $stmt = db()->prepare("SELECT a.id, a.title, a.summary, a.cover_image, a.created_at, c.name AS category FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.status = 'published' ORDER BY a.created_at DESC LIMIT ?");
                $stmt->execute([$limit]);
            }
            $articles = $stmt->fetchAll();
            return ['articles' => $articles, 'count' => count($articles)];

        default:
            return ['error' => "未知工具: {$name}"];
    }
}

// ===== 创建对话 =====
function createConversation(?int $userId, string $sessionId, string $title, string $model = ''): int
{
    if (!$model) {
        try {
            require_once __DIR__ . '/includes/mimo_service.php';
            $mimo = new MimoService();
            $model = $mimo->getDefaultModel();
        } catch (Exception $e) {
            $model = 'mimo-v2.5';
        }
    }
    $stmt = db()->prepare("INSERT INTO mimo_conversations (user_id, session_id, title, model) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $title, $model]);
    return (int)db()->lastInsertId();
}
