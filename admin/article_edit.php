<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin', 'editor')) {
    redirect('/myweb/login.php?redirect=/myweb/admin/article_edit.php');
}

require_once __DIR__ . '/../includes/article_service.php';
require_once __DIR__ . '/../includes/tag_helper.php';
require_once __DIR__ . '/../includes/article_validation.php';

$id    = (int)($_GET['id'] ?? 0);
$error = '';

// --- 加载数据 ---
if ($id) {
    $article = ArticleService::getArticleById($id);
    if (!$article) redirect('/myweb/admin/articles.php');
    $articleTags = ArticleService::getArticleTags($id);
} else {
    $article     = ['title' => '', 'content' => '', 'summary' => '', 'category_id' => 0, 'cover_image' => '', 'status' => 'draft'];
    $articleTags = [];
}

$categories = db()->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$allTags    = getAllTags();

// --- POST 处理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $role   = currentUser()['role'] ?? 'user';
    $status = ArticleStatus::adjustForRole($_POST['status'] ?? 'draft', $role);

    $data = [
        'title'       => trim($_POST['title'] ?? ''),
        'content'     => $_POST['content'] ?? '',
        'summary'     => trim($_POST['summary'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'status'      => $status,
    ];

    $errors      = validateArticleInput($data);
    $cover_image = $article['cover_image'];

    if (empty($errors) && !empty($_FILES['cover_image']['name'])) {
        $coverErr = validateCoverImage($_FILES['cover_image']);
        if ($coverErr) {
            $errors[] = $coverErr;
        } else {
            $cover_image = processCoverImage($_FILES['cover_image']);
        }
    }

    if (empty($errors)) {
        if ($id) {
            ArticleService::updateArticle($id, $data + ['cover_image' => $cover_image]);
        } else {
            $id = ArticleService::createArticle($data + [
                'cover_image' => $cover_image,
                'author_id'   => $_SESSION['user_id'],
            ]);
        }
        syncArticleTags($id, $_POST['tags'] ?? []);
        redirect('/myweb/admin/articles.php');
    } else {
        $error = $errors[0];
        // 保留表单输入
        $article['title']       = $data['title'];
        $article['content']     = $data['content'];
        $article['summary']     = $data['summary'];
        $article['category_id'] = $data['category_id'];
        $article['status']      = $data['status'];
    }
}

// --- 渲染 ---
require __DIR__ . '/views/article_form.php';
?>
<script src="/myweb/js/editor.js" defer></script>
<?php require_once '../includes/footer.php'; ?>
