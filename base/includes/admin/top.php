<?php
// Shared top of every staff view: site header, admin tab bar and a photo banner.
// Expects $pageTitle, $activeTab, $adminHeading and (optionally) $adminIntro.
defined('IN_ADMIN') or exit;
require __DIR__ . '/../header.php';
?>
<section class="admin-bar">
    <div class="container admin-bar-inner">
        <nav class="admin-tabs" aria-label="Staff">
            <?php foreach ($adminViews as $key => $tab): ?>
                <a href="admin.php?view=<?= e($key) ?>"<?= $key === $activeTab ? ' class="active" aria-current="page"' : '' ?>><?= e($tab[0]) ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="post" action="admin.php" class="admin-user">
            <?= csrfField() ?>
            <span class="muted">Signed in as <?= e($_SESSION['admin_name']) ?></span>
            <button class="link-button" type="submit" name="action" value="logout">Log out</button>
        </form>
    </div>
</section>

<section class="admin-hero">
    <div class="container">
        <div class="admin-hero-inner">
            <img src="<?= e($adminViews[$activeTab][1]) ?>" alt="" loading="lazy">
            <div class="admin-hero-text">
                <?php if (!empty($adminCrumb)): ?>
                    <p class="admin-crumb"><a href="admin.php?view=<?= e($activeTab) ?>"><?= e($adminViews[$activeTab][0]) ?></a> / <?= e($adminCrumb) ?></p>
                <?php endif; ?>
                <h1><?= e($adminHeading) ?></h1>
                <?php if (!empty($adminIntro)): ?><p><?= e($adminIntro) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
</section>
