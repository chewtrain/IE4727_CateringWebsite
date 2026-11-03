<?php
// Staff view: list packages, delete them, and warn about packages that cannot be ordered.
defined('IN_ADMIN') or exit;

$pdo = db();

if (isPost() && postValue('action') === 'delete_package') {
    checkCsrf();
    // Rules and dish links are removed by ON DELETE CASCADE; past orders keep
    // their copied package name and price (order_items.package_id becomes NULL).
    $stmt = $pdo->prepare('DELETE FROM packages WHERE id = ?');
    $stmt->execute([(int) postValue('package_id')]);
    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Package deleted.' : 'That package was already deleted.');
    redirect('admin.php?view=packages');
}

$packages = $pdo->query(
    'SELECT p.*,
            (SELECT COALESCE(SUM(r.choose_count), 0) FROM package_rules r WHERE r.package_id = p.id) AS dishes_to_pick,
            (SELECT COUNT(*) FROM package_dishes pd WHERE pd.package_id = p.id) AS dishes_offered,
            (SELECT COUNT(*) FROM order_items i WHERE i.package_id = p.id) AS times_ordered
     FROM packages p
     ORDER BY p.sort_order, p.name'
)->fetchAll();

// Package categories where too few dishes are available to meet the "choose N" rule.
$shortfalls = $pdo->query(
    'SELECT p.name AS package_name, c.name AS category_name, r.choose_count, COUNT(d.id) AS available
     FROM package_rules r
     JOIN packages p   ON p.id = r.package_id
     JOIN categories c ON c.id = r.category_id
     LEFT JOIN package_dishes pd ON pd.package_id = r.package_id
     LEFT JOIN dishes d ON d.id = pd.dish_id AND d.category_id = r.category_id AND d.is_active = 1
     WHERE p.is_active = 1
     GROUP BY r.package_id, r.category_id, p.name, c.name, r.choose_count
     HAVING COUNT(d.id) < r.choose_count'
)->fetchAll();

$pageTitle = 'Packages | Staff';
$adminHeading = 'Packages';
$adminIntro = 'The buffets customers can order: prices, minimum guests, menus and photos.';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">
        <?php foreach ($shortfalls as $gap): ?>
            <p class="alert alert-error">
                <?= e($gap['package_name']) ?> asks customers to choose <?= (int) $gap['choose_count'] ?>
                <?= e($gap['category_name']) ?>, but only <?= (int) $gap['available'] ?> are available.
                Customers cannot order it until more dishes are offered or switched on.
            </p>
        <?php endforeach; ?>

        <div class="toolbar">
            <p class="muted"><?= count($packages) ?> packages</p>
            <a class="btn btn-primary btn-small" href="admin.php?view=package">New package</a>
        </div>

        <div class="admin-cards">
            <?php foreach ($packages as $pkg): ?>
                <article class="admin-card<?= $pkg['is_active'] ? '' : ' is-off' ?>">
                    <img src="<?= e($pkg['image']) ?>" alt="<?= e($pkg['image_alt']) ?>" loading="lazy">
                    <div class="admin-card-body">
                        <h2><?= e($pkg['name']) ?></h2>
                        <p class="muted"><?= e($pkg['tagline']) ?></p>
                        <ul class="package-facts">
                            <li><strong><?= money($pkg['price_per_pax']) ?></strong> per guest</li>
                            <li>Min. <?= (int) $pkg['min_pax'] ?></li>
                            <li>Choose <?= (int) $pkg['dishes_to_pick'] ?> of <?= (int) $pkg['dishes_offered'] ?></li>
                            <li>Ordered <?= (int) $pkg['times_ordered'] ?>x</li>
                        </ul>
                        <p class="admin-card-tags">
                            <span class="badge <?= $pkg['is_active'] ? 'badge-delivered' : '' ?>"><?= $pkg['is_active'] ? 'On sale' : 'Hidden' ?></span>
                            <?php if ($pkg['is_featured']): ?><span class="badge badge-confirmed">On homepage</span><?php endif; ?>
                        </p>
                        <div class="admin-card-actions">
                            <a class="btn btn-secondary btn-small" href="admin.php?view=package&amp;id=<?= (int) $pkg['id'] ?>">Edit</a>
                            <form method="post" action="admin.php?view=packages"
                                  data-confirm="Delete <?= e($pkg['name']) ?>? Past orders keep their details, but the package disappears from the site.">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_package">
                                <input type="hidden" name="package_id" value="<?= (int) $pkg['id'] ?>">
                                <button class="link-button link-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
