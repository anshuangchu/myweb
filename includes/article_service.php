<?php
/**
 * ArticleService — 文章数据服务
 * 集中所有文章相关数据库查询，消除 index.php / admin/articles.php / article.php 间的重复。
 */
class ArticleService
{
    /**
     * 获取已发布文章列表（首页用，参数化查询）
     *
     * @return array{articles: array, page: int, perPage: int, total: int, totalPages: int}
     */
    public static function getPublishedArticles(
        string $sort = 'date',
        int $page = 1,
        int $perPage = 10,
        int $categoryId = 0,
        array $excludeIds = []
    ): array {
        $order  = sortField($sort);
        $pdo    = db();
        $where  = "a.status = 'published'";
        $params = [];

        if ($categoryId > 0) {
            $where .= ' AND a.category_id = ?';
            $params[] = $categoryId;
        }
        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $where .= " AND a.id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeIds);
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM articles a WHERE $where");
        $countStmt->execute($params);
        $total      = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = max(1, min($page, $totalPages));
        $offset     = ($page - 1) * $perPage;

        $sql = "SELECT a.*, u.username, c.name AS category_name
                FROM articles a
                LEFT JOIN users u ON a.author_id = u.id
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE $where
                ORDER BY $order
                LIMIT $perPage OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'articles'   => $stmt->fetchAll(),
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * 获取后台文章列表（全部状态）
     */
    public static function getAdminArticles(
        string $sort = 'date',
        int $page = 1,
        int $perPage = 20
    ): array {
        $order = sortField($sort);
        $pdo   = db();

        $total      = (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = max(1, min($page, $totalPages));
        $offset     = ($page - 1) * $perPage;

        $articles = $pdo->query(
            "SELECT a.*, u.username, c.name AS category_name
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN categories c ON a.category_id = c.id
             ORDER BY $order
             LIMIT $perPage OFFSET $offset"
        )->fetchAll();

        return compact('articles', 'page', 'perPage', 'total', 'totalPages');
    }

    /** 获取单篇文章（含作者名、分类名） */
    public static function getArticleById(int $id): ?array
    {
        $stmt = db()->prepare(
            "SELECT a.*, u.username, c.name AS category_name
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN categories c ON a.category_id = c.id
             WHERE a.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** 获取文章标签 */
    public static function getArticleTags(int $articleId): array
    {
        $stmt = db()->prepare(
            "SELECT t.id, t.name FROM tags t
             JOIN article_tags at ON t.id = at.tag_id
             WHERE at.article_id = ?
             ORDER BY t.name"
        );
        $stmt->execute([$articleId]);
        return $stmt->fetchAll();
    }

    /** 获取上下篇文章 */
    public static function getAdjacentArticles(int $articleId, string $createdAt): array
    {
        $prev = db()->prepare(
            "SELECT id, title FROM articles
             WHERE status = 'published' AND created_at < ?
             ORDER BY created_at DESC LIMIT 1"
        );
        $prev->execute([$createdAt]);

        $next = db()->prepare(
            "SELECT id, title FROM articles
             WHERE status = 'published' AND created_at > ?
             ORDER BY created_at ASC LIMIT 1"
        );
        $next->execute([$createdAt]);

        return ['prev' => $prev->fetch() ?: null, 'next' => $next->fetch() ?: null];
    }

    /** 增加浏览量（同会话只计一次） */
    public static function incrementViews(int $articleId): void
    {
        if (empty($_SESSION['viewed_articles'][$articleId])) {
            db()->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$articleId]);
            $_SESSION['viewed_articles'][$articleId] = true;
        }
    }

    /** 获取特色文章（最新 N 篇） */
    public static function getFeaturedArticles(int $limit = 5): array
    {
        return db()->query(
            "SELECT a.*, u.username, c.name AS category_name
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN categories c ON a.category_id = c.id
             WHERE a.status = 'published'
             ORDER BY a.created_at DESC
             LIMIT $limit"
        )->fetchAll();
    }

    /** 获取文章统计 */
    public static function getStats(): array
    {
        $total     = (int)db()->query("SELECT COUNT(*) FROM articles")->fetchColumn();
        $published = (int)db()->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();
        $pending   = (int)db()->query("SELECT COUNT(*) FROM articles WHERE status='pending'")->fetchColumn();
        return compact('total', 'published', 'pending');
    }

    /** 创建文章，返回新 ID */
    public static function createArticle(array $data): int
    {
        $stmt = db()->prepare(
            "INSERT INTO articles (title, content, summary, category_id, cover_image, status, author_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['summary'] ?? '',
            $data['category_id'] ?: null,
            $data['cover_image'] ?? '',
            $data['status'],
            $data['author_id'],
        ]);
        return (int)db()->lastInsertId();
    }

    /** 更新文章 */
    public static function updateArticle(int $id, array $data): void
    {
        $stmt = db()->prepare(
            "UPDATE articles
             SET title = ?, content = ?, summary = ?, category_id = ?, cover_image = ?, status = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['summary'] ?? '',
            $data['category_id'] ?: null,
            $data['cover_image'] ?? '',
            $data['status'],
            $id,
        ]);
    }
}
