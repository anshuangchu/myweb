<?php
/**
 * SearchService — 统一搜索服务
 *
 * 利用数据库 FULLTEXT 索引进行高效搜索，短关键词/无结果时 fallback 到 LIKE
 */

class SearchService
{
    /** 全文搜索最短关键词长度（MySQL 默认 ft_min_word_len=3，中文不受限） */
    private const MIN_FULLTEXT_LEN = 3;

    /** 搜索结果上限 */
    private const MAX_RESULTS = 50;

    // ================================================================
    //  公共搜索方法
    // ================================================================

    /**
     * 搜索文章（优先 FULLTEXT，fallback LIKE）
     *
     * @param string $q     搜索关键词
     * @param int    $limit 结果上限
     * @return array
     */
    public static function searchArticles(string $q, int $limit = self::MAX_RESULTS): array
    {
        $q = trim($q);
        if ($q === '') return [];

        $results = self::fulltextSearchArticles($q, $limit);
        if (empty($results)) {
            $results = self::likeSearchArticles($q, $limit);
        }
        return $results;
    }

    /**
     * 搜索资料（优先 FULLTEXT，fallback LIKE）
     */
    public static function searchPages(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') return [];

        $results = self::fulltextSearchPages($q, $limit);
        if (empty($results)) {
            $results = self::likeSearchPages($q, $limit);
        }
        return $results;
    }

    /**
     * 完整搜索：同时搜索文章和资料
     *
     * @return array{articles: array, pages: array, total: int}
     */
    public static function search(string $q, int $articleLimit = 50, int $pageLimit = 20): array
    {
        $articles = self::searchArticles($q, $articleLimit);
        $pages = self::searchPages($q, $pageLimit);

        return [
            'articles' => $articles,
            'pages'    => $pages,
            'total'    => count($articles) + count($pages),
        ];
    }

    // ================================================================
    //  FULLTEXT 搜索
    // ================================================================

    /**
     * MATCH...AGAINST 全文搜索文章
     * 数据库已建立 ft_articles_search (title, content, summary) 索引
     */
    private static function fulltextSearchArticles(string $q, int $limit): array
    {
        try {
            $mode = self::buildFulltextQuery($q);
            $stmt = db()->prepare(
                "SELECT a.*, u.username, c.name AS category_name
                 FROM articles a
                 LEFT JOIN users u ON a.author_id = u.id
                 LEFT JOIN categories c ON a.category_id = c.id
                 WHERE a.status = 'published'
                   AND MATCH(a.title, a.content, a.summary) AGAINST(:q IN BOOLEAN MODE)
                 ORDER BY a.created_at DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':q', $mode, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // FULLTEXT 查询失败（如关键词太短）→ 回退到 LIKE
            return [];
        }
    }

    /**
     * MATCH...AGAINST 全文搜索资料
     */
    private static function fulltextSearchPages(string $q, int $limit): array
    {
        try {
            $mode = self::buildFulltextQuery($q);
            $stmt = db()->prepare(
                "SELECT * FROM pages
                 WHERE status = 'published'
                   AND MATCH(title, content) AGAINST(:q IN BOOLEAN MODE)
                 ORDER BY created_at DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':q', $mode, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ================================================================
    //  LIKE fallback 搜索
    // ================================================================

    private static function likeSearchArticles(string $q, int $limit): array
    {
        $keyword = '%' . $q . '%';
        $stmt = db()->prepare(
            "SELECT a.*, u.username, c.name AS category_name
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN categories c ON a.category_id = c.id
             WHERE a.status = 'published'
               AND (a.title LIKE :kw1 OR a.summary LIKE :kw2 OR a.content LIKE :kw3)
             ORDER BY a.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':kw1', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':kw2', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':kw3', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function likeSearchPages(string $q, int $limit): array
    {
        $keyword = '%' . $q . '%';
        $stmt = db()->prepare(
            "SELECT * FROM pages
             WHERE status = 'published'
               AND (title LIKE :kw1 OR content LIKE :kw2)
             ORDER BY created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':kw1', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':kw2', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ================================================================
    //  辅助方法
    // ================================================================

    /**
     * 将搜索关键词转换为 BOOLEAN MODE 查询字符串
     *
     * 对中文：每个词加 + 前缀（要求出现）
     * 对英文：单词加 * 通配符
     */
    private static function buildFulltextQuery(string $q): string
    {
        // 分词：中文字符间插入空格分隔
        $hasCJK = preg_match('/[\x{4e00}-\x{9fff}]/u', $q);

        if ($hasCJK) {
            // 中文：逐字符 + 加通配符
            $chars = preg_split('//u', $q, -1, PREG_SPLIT_NO_EMPTY);
            $terms = [];
            foreach ($chars as $ch) {
                if (mb_strlen(trim($ch)) > 0) {
                    $terms[] = '+' . $ch . '*';
                }
            }
            return implode(' ', $terms);
        }

        // 英文/混合：给每个词加 + 前缀
        $words = preg_split('/\s+/', $q);
        $terms = [];
        foreach ($words as $word) {
            $word = trim($word);
            if ($word !== '') {
                $terms[] = '+' . $word . '*';
            }
        }
        return implode(' ', $terms);
    }
}
