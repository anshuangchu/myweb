</main>
<?php
$friendLinks = db()->query("SELECT * FROM links ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<?php if ($friendLinks): ?>
<div class="friend-links">
    <div class="container">
        <div class="friend-links-inner">
            <span class="friend-links-label">🖇️</span>
            <?php $i = 0; $total = count($friendLinks); ?>
            <?php foreach ($friendLinks as $l): ?>
            <a href="<?= htmlspecialchars($l['url']) ?>" class="friend-link" target="_blank" rel="noopener"><?= htmlspecialchars($l['name']) ?></a>
            <?php if (++$i < $total): ?><span class="friend-link-sep"></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> <a href="/myweb/"><?= $siteName ?></a>. Powered by DeepSeek.</p>
    </div>
</footer>
</body>
</html>
