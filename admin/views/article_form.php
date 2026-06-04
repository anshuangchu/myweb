<?php
/**
 * 文章编辑表单视图（单栏布局 + 实时预览）
 *
 * 接收变量（由 article_edit.php 传入）:
 * @var array  $article      ['title','content','summary','category_id','cover_image','status']
 * @var array  $categories   [['id'=>int, 'name'=>string], ...]
 * @var array  $articleTags  [['id'=>int, 'name'=>string], ...]
 * @var array  $allTags      [['id'=>int, 'name'=>string], ...]
 * @var string $error        错误消息（空字符串 = 无错误）
 * @var int    $id           文章 ID（0 = 新建）
 */
?>
<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main" style="padding:24px 32px">
        <form method="post" enctype="multipart/form-data" id="articleForm">
        <?= csrfField() ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <input type="text" name="title" class="ed-title" required
               value="<?= htmlspecialchars($article['title']) ?>" placeholder="输入文章标题...">

        <!-- 格式工具栏 -->
        <div class="ed-ribbon">
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="wrap('h2')" title="二级标题"><b>H2</b></button>
                <button type="button" class="ed-btn" onclick="wrap('h3')" title="三级标题"><b>H3</b></button>
                <button type="button" class="ed-btn" onclick="wrap('h4')" title="四级标题">H4</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="wrap('strong')" title="加粗"><b>B</b></button>
                <button type="button" class="ed-btn" onclick="wrap('em')" title="斜体"><i>I</i></button>
                <button type="button" class="ed-btn" onclick="wrap('u')" title="下划线"><u>U</u></button>
                <button type="button" class="ed-btn" onclick="wrap('code')" title="行内代码">&lt;/&gt;</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="insertBlock('pre')" title="代码块">&#x7f52; 代码块</button>
                <button type="button" class="ed-btn" onclick="insertBlock('blockquote')" title="引用">&#x7f51; 引用</button>
                <button type="button" class="ed-btn" onclick="insertWarn()" title="注意事项">&#x7f52; 警告</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="insertList('ul')" title="无序列表">&#x25cf; 列表</button>
                <button type="button" class="ed-btn" onclick="insertList('ol')" title="有序列表">1. 列表</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="insertLink()" title="插入链接">&#x1f517; 链接</button>
                <button type="button" class="ed-btn" onclick="insertImg()" title="插入图片">&#x1f5bc; 图片</button>
                <button type="button" class="ed-btn" onclick="insertHR()" title="分割线">&#x2501;</button>
            </div>
            <div style="flex:1"></div>
            <button type="button" class="ed-btn ed-btn-accent" id="btnAiFormat" onclick="runAiFormat()" title="AI 智能排版">&#x2728; AI 排版</button>
            <button type="button" class="ed-btn ed-btn-accent" onclick="togglePreview()" title="切换预览">&#x1f441; 预览</button>
        </div>

        <!-- 编辑器 + 预览 双栏 -->
        <div class="ed-area">
            <textarea name="content" id="editor" class="ed-textarea"
                      placeholder="开始写作... 使用上方按钮格式化文本"><?= htmlspecialchars($article['content']) ?></textarea>
            <div class="ed-preview" id="previewPane">
                <div class="ed-preview-inner article-content" id="previewContent"></div>
            </div>
        </div>

        <!-- 底部操作栏 -->
        <div class="ed-foot">
            <button type="submit" class="btn btn-primary"><?= $id ? '保存修改' : '发布文章' ?></button>
            <a href="/myweb/admin/articles.php" class="btn">取消</a>
            <span class="ed-foot-status">Ctrl+S 保存 | 选中文字后点击格式按钮 | 编辑时自动预览</span>
        </div>

        <!-- 文章设置（可折叠） -->
        <div class="ed-settings">
            <button type="button" class="ed-settings-toggle" onclick="toggleSettings()">
                <span class="ed-settings-arrow" id="settingsArrow">&#x25b6;</span> 文章设置
            </button>
            <div class="ed-settings-body" id="settingsBody" style="display:none">
                <div class="ed-settings-grid">
                    <!-- 发布设置 -->
                    <div class="es-card">
                        <div class="es-card-title">发布设置</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>状态</label>
                                <select name="status" class="e-select" style="width:100%">
                                    <?php foreach (ArticleStatus::ALL as $s): ?>
                                    <option value="<?= $s ?>" <?= $article['status'] === $s ? 'selected' : '' ?>>
                                        <?= ArticleStatus::label($s) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>分类</label>
                                <select name="category_id" class="e-select" style="width:100%">
                                    <option value="">无分类</option>
                                    <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === (int)$article['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 封面图片 -->
                    <div class="es-card">
                        <div class="es-card-title">封面图片</div>
                        <input type="file" name="cover_image" accept="image/*" style="font-size:0.82rem">
                        <?php if ($article['cover_image']): ?>
                        <img src="/myweb/<?= htmlspecialchars($article['cover_image']) ?>" alt="" style="max-width:200px;margin-top:8px;border-radius:8px;display:block">
                        <?php endif; ?>
                    </div>

                    <!-- 摘要 -->
                    <div class="es-card">
                        <div class="es-card-title">摘要</div>
                        <textarea name="summary" rows="2" placeholder="可选（留空自动从正文提取前 200 字）" style="width:100%;padding:8px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;color:var(--gray-200);font-size:0.85rem;resize:vertical"><?= htmlspecialchars($article['summary']) ?></textarea>
                    </div>

                    <!-- 标签 -->
                    <?= renderTagInput($articleTags, $allTags) ?>
                </div>
            </div>
        </div>
        </form>
    </main>
</div>
