<?php
/**
 * AI 搜索增强 — AJAX 端点
 *
 * GET /ai_search.php?q=xxx
 * 对用户查询进行 AI 语义扩展，返回 AI 排序后的搜索结果。
 * 速率限制：20 次/小时/IP
 *
 * 返回: JSON { success: bool, data?: {keywords:[], articles:[], pages:[]}, error?: string }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db_loader.php';
require_once __DIR__ . '/includes/search_service.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ================================================================
//  输入验证
// ================================================================
$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['success' => false, 'error' => '请输入至少 2 个字符的搜索关键词'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================================================================
//  速率限制（20 次/小时/IP）
// ================================================================
$rateLimitKey = 'ai_search_rate_' . md5(getClientIP());
$rateLimit = $_SESSION[$rateLimitKey] ?? ['count' => 0, 'reset' => time() + 3600];

if (time() > $rateLimit['reset']) {
    $rateLimit = ['count' => 0, 'reset' => time() + 3600];
}

$rateLimit['count']++;
$_SESSION[$rateLimitKey] = $rateLimit;

if ($rateLimit['count'] > 20) {
    $waitMin = ceil(($rateLimit['reset'] - time()) / 60);
    echo json_encode(['success' => false, 'error' => "请求过于频繁，请 $waitMin 分钟后再试"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================================================================
//  Session 缓存管理
// ================================================================
$cacheKey = 'ai_search_' . md5($q);

// 从缓存读取（5 分钟有效）
if (!empty($_SESSION[$cacheKey]) && time() - ($_SESSION[$cacheKey . '_time'] ?? 0) < 300) {
    echo json_encode(['success' => true, 'data' => $_SESSION[$cacheKey]], JSON_UNESCAPED_UNICODE);
    exit;
}

// 清理旧缓存（保留最近 20 条）
$allKeys = array_keys($_SESSION);
$searchKeys = array_filter($allKeys, fn($k) => str_starts_with($k, 'ai_search_') && !str_ends_with($k, '_time'));
if (count($searchKeys) > 20) {
    $oldest = [];
    foreach ($searchKeys as $k) {
        $oldest[$k] = $_SESSION[$k . '_time'] ?? 0;
    }
    asort($oldest);
    $toRemove = array_slice(array_keys($oldest), 0, count($oldest) - 20);
    foreach ($toRemove as $k) {
        unset($_SESSION[$k], $_SESSION[$k . '_time']);
    }
}

// ================================================================
//  AI 搜索逻辑
// ================================================================
try {
    require_once __DIR__ . '/includes/ai_service.php';
    $ai = new AiService();

    // 1. AI 扩展关键词
    $keywords = $ai->expandSearchQuery($q);

    // 2. 使用 SearchService 搜索（FULLTEXT 优先）
    $allArticles = [];
    $allArticleIds = [];
    $allPages = [];
    $allPageIds = [];

    foreach ($keywords as $kw) {
        $kwResults = SearchService::searchArticles($kw, 30);
        foreach ($kwResults as $row) {
            if (!isset($allArticleIds[$row['id']])) {
                $allArticleIds[$row['id']] = true;
                $allArticles[] = $row;
            }
        }

        $kwPages = SearchService::searchPages($kw, 10);
        foreach ($kwPages as $row) {
            $key = 'p_' . $row['id'];
            if (!isset($allPageIds[$key])) {
                $allPageIds[$key] = true;
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
            $sortedIds = array_column($sorted, 'id');
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

    // 4. 格式化输出
    $articlesOut = array_map(fn($a) => [
        'id'            => (int) $a['id'],
        'title'         => htmlspecialchars($a['title']),
        'username'      => htmlspecialchars($a['username']),
        'category_name' => htmlspecialchars($a['category_name'] ?? '未分类'),
        'created_at'    => $a['created_at'],
        'summary'       => htmlspecialchars($a['summary'] ?: mb_substr(strip_tags($a['content']), 0, 200)),
    ], $allArticles);

    $pagesOut = array_map(fn($p) => [
        'id'         => (int) $p['id'],
        'title'      => htmlspecialchars($p['title']),
        'slug'       => htmlspecialchars($p['slug']),
        'created_at' => $p['created_at'],
    ], $allPages);

    $data = [
        'keywords'  => array_map('htmlspecialchars', $keywords),
        'articles'  => $articlesOut,
        'pages'     => $pagesOut,
    ];

    // 缓存（5 分钟）
    $_SESSION[$cacheKey] = $data;
    $_SESSION[$cacheKey . '_time'] = time();

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $msg = DEBUG_MODE ? $e->getMessage() : 'AI 服务暂时不可用';
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
