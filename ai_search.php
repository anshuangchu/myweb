<?php
/**
 * AI 搜索增强 — AJAX 端点
 *
 * GET /ai_search.php?q=xxx
 * 对用户查询进行 AI 语义扩展，返回扩展后的搜索结果。
 *
 * 返回: JSON { success: bool, data?: {keywords:[], articles:[], pages:[]}, error?: string }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db_loader.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$q = trim($_GET['q'] ?? '');
if (!$q || mb_strlen($q) < 1) {
    echo json_encode(['success' => false, 'error' => '请输入搜索关键词']);
    exit;
}

// Session 缓存 key（5 分钟有效期）
$cacheKey = 'ai_search_' . md5($q);
if (!empty($_SESSION[$cacheKey]) && time() - ($_SESSION[$cacheKey . '_time'] ?? 0) < 300) {
    echo json_encode(['success' => true, 'data' => $_SESSION[$cacheKey]]);
    exit;
}
unset($_SESSION[$cacheKey], $_SESSION[$cacheKey . '_time']);

try {
    require_once __DIR__ . '/includes/ai_service.php';
    $ai = new AiService();

    // 1. AI 扩展关键词
    $keywords = $ai->expandSearchQuery($q);

    // 2. 使用扩展关键词搜索
    $allArticleIds = [];
    $allArticles = [];
    $allPages = [];

    foreach ($keywords as $kw) {
        $keyword = '%' . $kw . '%';

        $stmt = db()->prepare("SELECT a.*, u.username, c.name as category_name
            FROM articles a
            LEFT JOIN users u ON a.author_id = u.id
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.status = 'published' AND (a.title LIKE ? OR a.summary LIKE ?)
            ORDER BY a.views DESC
            LIMIT 30");
        $stmt->execute([$keyword, $keyword]);
        foreach ($stmt->fetchAll() as $row) {
            if (!isset($allArticleIds[$row['id']])) {
                $allArticleIds[$row['id']] = true;
                $allArticles[] = $row;
            }
        }

        // 搜索资料
        $stmt = db()->prepare("SELECT * FROM pages WHERE status='published' AND (title LIKE ? OR content LIKE ?) ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$keyword, $keyword]);
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['id'] . '_page';
            if (!isset($allArticleIds[$key])) {
                $allArticleIds[$key] = true;
                $allPages[] = $row;
            }
        }
    }

    // 3. AI 排序（文章数 > 1 时）
    if (count($allArticles) > 1) {
        $items = [];
        foreach ($allArticles as $a) {
            $items[] = [
                'id'      => $a['id'],
                'title'   => $a['title'],
                'summary' => mb_substr(strip_tags($a['summary'] ?: $a['content']), 0, 100),
            ];
        }
        try {
            $sorted = $ai->rankResults($q, $items);
            // 重建排序后的数组
            $sortedIds = array_map(fn($i) => $i['id'], $sorted);
            $articleMap = [];
            foreach ($allArticles as $a) $articleMap[$a['id']] = $a;
            $allArticles = [];
            foreach ($sortedIds as $id) {
                if (isset($articleMap[$id])) $allArticles[] = $articleMap[$id];
            }
        } catch (Exception $e) {
            // AI 排序失败，保留原顺序
        }
    }

    // 5. 格式化输出
    $articlesOut = [];
    foreach ($allArticles as $a) {
        $articlesOut[] = [
            'id'            => (int)$a['id'],
            'title'         => htmlspecialchars($a['title']),
            'username'      => htmlspecialchars($a['username']),
            'category_name' => htmlspecialchars($a['category_name'] ?? '未分类'),
            'created_at'    => $a['created_at'],
            'summary'       => htmlspecialchars($a['summary'] ?: mb_substr(strip_tags($a['content']), 0, 200)),
        ];
    }

    $pagesOut = [];
    foreach ($allPages as $p) {
        $pagesOut[] = [
            'id'         => (int)$p['id'],
            'title'      => htmlspecialchars($p['title']),
            'slug'       => htmlspecialchars($p['slug']),
            'created_at' => $p['created_at'],
        ];
    }

    $escapedKeywords = array_map('htmlspecialchars', $keywords);
    $data = [
        'keywords'  => $escapedKeywords,
        'articles'  => $articlesOut,
        'pages'     => $pagesOut,
    ];

    // 缓存到 session（5 分钟有效期）
    $_SESSION[$cacheKey] = $data;
    $_SESSION[$cacheKey . '_time'] = time();

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
