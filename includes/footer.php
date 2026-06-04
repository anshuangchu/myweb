</main><!-- /.main-content -->

<?php
$friendLinks = db()->query("SELECT * FROM links ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<footer class="site-footer">
    <?php if ($friendLinks): ?>
    <div class="footer-links">
        <?php foreach ($friendLinks as $l): ?>
        <a href="<?= htmlspecialchars($l['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($l['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> <?= $siteName ?></span>
        <span class="footer-sep">·</span>
        <span>基于 <a href="https://www.deepseek.com/" target="_blank" rel="noopener noreferrer" style="color:var(--accent-light);text-decoration:none">DeepSeek</a> + Reasonix 工作流重构</span>
    </div>
</footer>

<?php if (isLoggedIn() && (empty($settings['ai_enabled']) || $settings['ai_enabled'] !== '0')): ?>
<?php require_once __DIR__ . '/chat_widget.php'; ?>
<?php endif; ?>
</body>
</html>