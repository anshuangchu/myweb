<?php
/**
 * AI 文章推荐 — AJAX 端点
 *
 * GET /ai_recommend.php?id=1
 * 返回: JSON { success: bool, data?: array, error?: string }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db_loader.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$articleId = (int)($_GET['id'] ?? 0);
if (!$articleId) {
    echo json_encode(['success' => false, 'error' => '缺少文章 ID']);
    exit;
}

// 获取当前文章的分类
$stmt = db()->prepare("SELECT id, title, summary, category_id FROM articles WHERE id = ?");
$stmt->execute([$articleId]);
$current = $stmt->fetch();
if (!$current) {
    echo json_encode(['success' => false, 'error' => '文章不存在']);
    exit;
}

// 找同分类的其他已发布文章（排除自身）
$candidates = [];
if ($current['category_id']) {
    $stmt = db()->prepare("SELECT id, title, summary, created_at FROM articles WHERE category_id = ? AND status = 'published' AND id != ? ORDER BY views DESC, created_at DESC LIMIT 10");
    $stmt->execute([$current['category_id'], $articleId]);
    $candidates = $stmt->fetchAll();
}

// 同分类不足时补充最新文章
if (count($candidates) < 5) {
    $excludeIds = array_merge([$articleId], array_column($candidates, 'id'));
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $stmt = db()->prepare("SELECT id, title, summary, created_at FROM articles WHERE status = 'published' AND id NOT IN ($placeholders) ORDER BY created_at DESC LIMIT ?");
    $params = array_merge($excludeIds, [10 - count($candidates)]);
    $stmt->execute($params);
    $candidates = array_merge($candidates, $stmt->fetchAll());
}

if (empty($candidates)) {
    echo json_encode(['success' => false, 'error' => '暂无相关文章']);
    exit;
}

// 尝试 AI 排序，失败则按时间降序返回
try {
    require_once __DIR__ . '/includes/ai_service.php';
    $ai = new AiService();

    $items = [];
    foreach ($candidates as $c) {
        $items[] = [
            'id'      => $c['id'],
            'title'   => $c['title'],
            'summary' => mb_substr(strip_tags($c['summary'] ?: ''), 0, 100),
        ];
    }
    $sorted = $ai->recommendFromCandidates(
        $current['title'],
        mb_substr(strip_tags($current['summary'] ?: ''), 0, 100),
        $items,
        5
    );
    $recommended = $sorted;

} catch (Exception $e) {
    // AI 排序失败，按最新返回
    $recommended = array_slice($candidates, 0, 5);
}

// 格式化输出
$result = [];
foreach ($recommended as $r) {
    $result[] = [
        'id'       => (int)$r['id'],
        'title'    => htmlspecialchars($r['title']),
        'summary'  => htmlspecialchars(mb_substr(strip_tags($r['summary'] ?: ''), 0, 120)),
        'date'     => date('m-d', strtotime($r['created_at'] ?? '')),
    ];
}

echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
