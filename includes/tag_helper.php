<?php
/**
 * TagHelper — 标签辅助函数
 * 集中管理标签的查询、渲染、同步操作
 */

/** 获取所有标签（按名称排序） */
function getAllTags(): array
{
    return db()->query("SELECT * FROM tags ORDER BY name")->fetchAll();
}

/** 获取标签列表（含文章计数，按使用量降序） */
function getTagsWithCounts(): array
{
    return db()->query(
        "SELECT t.*, (SELECT COUNT(*) FROM article_tags WHERE tag_id = t.id) AS article_count
         FROM tags t
         ORDER BY article_count DESC, t.name"
    )->fetchAll();
}

/**
 * 渲染标签输入组件（编辑器侧边栏用）
 *
 * @param array $existingTags 文章已有标签 [['id'=>int, 'name'=>string], ...]
 * @param array $allTags      所有可用标签 [['id'=>int, 'name'=>string], ...]
 * @return string HTML
 */
function renderTagInput(array $existingTags, array $allTags): string
{
    $existingNames = implode(', ', array_column($existingTags, 'name'));
    $escapedNames  = htmlspecialchars($existingNames);

    $html = '<div class="es-card">';
    $html .= '<div class="es-card-title">标签</div>';
    $html .= '<input type="text" id="tagInput" placeholder="空格或逗号分隔"';
    $html .= ' value="' . $escapedNames . '"';
    $html .= ' style="width:100%;padding:8px;background:var(--gray-900);border:1px solid var(--gray-700);';
    $html .= 'border-radius:6px;color:var(--gray-200);font-size:0.85rem">';
    $html .= '<div id="tagsHidden"></div>';
    $html .= '<div id="tagsChips" class="tag-chips" style="margin-top:6px"></div>';

    if ($allTags) {
        $html .= '<div class="tag-suggestions" style="margin-top:6px">';
        foreach ($allTags as $t) {
            $name = htmlspecialchars($t['name']);
            $json = htmlspecialchars(json_encode($t['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            $html .= '<span class="tag tag-suggest" onclick="addTag(' . $json . ')">' . $name . '</span>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * 同步文章标签（事务保护）
 * 先删除旧关联 → 创建新标签(INSERT IGNORE) → 建立关联
 */
function syncArticleTags(int $articleId, array $tagNames): void
{
    $tagNames = array_unique(array_map('trim', $tagNames));
    $tagNames = array_values(array_filter($tagNames, fn($n) => $n !== ''));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$articleId]);

        if (!empty($tagNames)) {
            $insertTag = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
            $insertRel = $pdo->prepare(
                "INSERT INTO article_tags (article_id, tag_id) SELECT ?, id FROM tags WHERE name = ?"
            );
            foreach ($tagNames as $name) {
                $insertTag->execute([$name]);
                $insertRel->execute([$articleId, $name]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * 添加单个标签（admin/tags.php 用）
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function addTagByName(string $name): array
{
    $name = trim($name);
    if ($name === '') {
        return ['success' => false, 'error' => '标签名不能为空'];
    }
    $stmt = db()->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
    $stmt->execute([$name]);
    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'error' => '标签已存在'];
    }
    return ['success' => true, 'error' => null];
}

/** 删除标签 */
function deleteTag(int $id): void
{
    db()->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
}
