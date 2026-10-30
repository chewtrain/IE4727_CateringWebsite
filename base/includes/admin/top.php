<?php
// Shared top of every staff view: site header plus the admin tab bar.
// Expects $pageTitle and $activeTab.
defined('IN_ADMIN') or exit;
require __DIR__ . '/../header.php';
?>
<section class="admin-bar">
    <div class="container admin-bar-inner">
        <nav class="admin-tabs" aria-label="Staff">
            <?php foreach ($adminViews as $key => $label): ?>
                <a href="admin.php?view=<?= e($key) ?>"<?= $key === $activeTab ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="post" action="admin.php" class="admin-user">
            <?= csrfField() ?>
            <span class="muted">Signed in as <?= e($_SESSION['admin_name']) ?></span>
            <button class="link-button" type="submit" name="action" value="logout">Log out</button>
        </form>
    </div>
</section>
