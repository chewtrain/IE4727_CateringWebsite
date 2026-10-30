<?php
// Staff view: edit package prices and visibility, switch dishes on/off, add dishes.
defined('IN_ADMIN') or exit;

$pdo = db();
$addErrors = [];
$newDish = ['name' => '', 'category_id' => '', 'description' => '', 'packages' => []];

if (isPost()) {
    checkCsrf();
    $action = postValue('action');

    // Update one package's price, minimum guests and flags.
    if ($action === 'update_package') {
        $id = (int) postValue('package_id');
        $price = postValue('price_per_pax');
        $minPax = postValue('min_pax');

        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $price) || (float) $price <= 0) {
            flash('error', 'Price must be a positive amount like 16.80.');
        } elseif (!ctype_digit($minPax) || (int) $minPax < 10 || (int) $minPax > MAX_GUESTS) {
            flash('error', 'Minimum guests must be between 10 and ' . MAX_GUESTS . '.');
        } else {
            $pdo->prepare('UPDATE packages SET price_per_pax = ?, min_pax = ?, is_featured = ?, is_active = ? WHERE id = ?')
                ->execute([$price, (int) $minPax, empty($_POST['is_featured']) ? 0 : 1, empty($_POST['is_active']) ? 0 : 1, $id]);
            flash('success', 'Package updated.');
        }
        redirect('admin.php?view=menu');
    }

    // Show or hide a dish on every menu.
    if ($action === 'toggle_dish') {
        $pdo->prepare('UPDATE dishes SET is_active = 1 - is_active WHERE id = ?')->execute([(int) postValue('dish_id')]);
        flash('success', 'Dish availability changed.');
        redirect('admin.php?view=menu#dishes');
    }

    // Add a new dish and offer it in the chosen packages.
    if ($action === 'add_dish') {
        $newDish['name'] = postValue('name');
        $newDish['category_id'] = postValue('category_id');
        $newDish['description'] = postValue('description');
        $newDish['packages'] = isset($_POST['packages']) && is_array($_POST['packages']) ? array_map('intval', $_POST['packages']) : [];

        if (strlen($newDish['name']) < 3 || strlen($newDish['name']) > 80) {
            $addErrors[] = 'Dish name must be 3 to 80 characters.';
        }
        $catCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $catCheck->execute([(int) $newDish['category_id']]);
        if (!$catCheck->fetchColumn()) {
            $addErrors[] = 'Choose a category.';
        }
        if (strlen($newDish['description']) > 200) {
            $addErrors[] = 'Description must be under 200 characters.';
        }
        if (!$newDish['packages']) {
            $addErrors[] = 'Offer the dish in at least one package.';
        }

        if (!$addErrors) {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO dishes (category_id, name, description, is_vegetarian, is_spicy) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $newDish['category_id'], $newDish['name'], $newDish['description'],
                           empty($_POST['is_vegetarian']) ? 0 : 1, empty($_POST['is_spicy']) ? 0 : 1]);
            $dishId = (int) $pdo->lastInsertId();
            $link = $pdo->prepare('INSERT INTO package_dishes (package_id, dish_id) SELECT id, ? FROM packages WHERE id = ?');
            foreach ($newDish['packages'] as $packageId) {
                $link->execute([$dishId, $packageId]);
            }
            $pdo->commit();
            flash('success', $newDish['name'] . ' was added to the menu.');
            redirect('admin.php?view=menu#dishes');
        }
    }
}

$packages = $pdo->query('SELECT * FROM packages ORDER BY sort_order')->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();

// Every dish with the packages that offer it and how often it has been ordered.
$dishes = $pdo->query(
    'SELECT d.*, c.name AS category_name,
            GROUP_CONCAT(p.name ORDER BY p.sort_order SEPARATOR ", ") AS package_names,
            (SELECT COUNT(*) FROM order_item_dishes oid WHERE oid.dish_id = d.id) AS times_ordered
     FROM dishes d
     JOIN categories c ON c.id = d.category_id
     LEFT JOIN package_dishes pd ON pd.dish_id = d.id
     LEFT JOIN packages p ON p.id = pd.package_id
     GROUP BY d.id
     ORDER BY c.sort_order, d.name'
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

$pageTitle = 'Menu | Staff';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">
        <div class="admin-head"><h1>Packages</h1></div>

        <?php foreach ($shortfalls as $gap): ?>
            <p class="alert alert-error">
                <?= e($gap['package_name']) ?> asks customers to choose <?= (int) $gap['choose_count'] ?>
                <?= e($gap['category_name']) ?>, but only <?= (int) $gap['available'] ?> are available.
                Customers cannot order it until more dishes are switched on.
            </p>
        <?php endforeach; ?>

        <?php foreach ($packages as $pkg): ?>
            <form id="pkg-<?= (int) $pkg['id'] ?>" method="post" action="admin.php?view=menu">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_package">
                <input type="hidden" name="package_id" value="<?= (int) $pkg['id'] ?>">
            </form>
        <?php endforeach; ?>

        <div class="table-wrap">
            <table class="table admin-table">
                <thead>
                    <tr>
                        <th scope="col">Package</th>
                        <th scope="col">Price per guest ($)</th>
                        <th scope="col">Min. guests</th>
                        <th scope="col">On homepage</th>
                        <th scope="col">On sale</th>
                        <th scope="col"><span class="sr-only">Save</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $pkg): ?>
                        <?php $formId = 'pkg-' . (int) $pkg['id']; ?>
                        <tr>
                            <th scope="row"><?= e($pkg['name']) ?></th>
                            <td><input class="input-small" form="<?= $formId ?>" type="number" name="price_per_pax" step="0.01" min="0.01" max="999" required value="<?= e($pkg['price_per_pax']) ?>" aria-label="Price per guest for <?= e($pkg['name']) ?>"></td>
                            <td><input class="input-small" form="<?= $formId ?>" type="number" name="min_pax" step="1" min="10" max="<?= MAX_GUESTS ?>" required value="<?= (int) $pkg['min_pax'] ?>" aria-label="Minimum guests for <?= e($pkg['name']) ?>"></td>
                            <td><input form="<?= $formId ?>" type="checkbox" name="is_featured" value="1" <?= $pkg['is_featured'] ? 'checked' : '' ?> aria-label="Show <?= e($pkg['name']) ?> on homepage"></td>
                            <td><input form="<?= $formId ?>" type="checkbox" name="is_active" value="1" <?= $pkg['is_active'] ? 'checked' : '' ?> aria-label="<?= e($pkg['name']) ?> on sale"></td>
                            <td><button class="btn btn-small btn-secondary" form="<?= $formId ?>" type="submit">Save</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-head" id="dishes"><h2>Dishes</h2></div>

        <div class="admin-split">
            <div class="table-wrap">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Dish</th>
                            <th scope="col">Category</th>
                            <th scope="col">In packages</th>
                            <th scope="col" class="num">Ordered</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dishes as $dish): ?>
                            <tr class="<?= $dish['is_active'] ? '' : 'row-muted' ?>">
                                <td>
                                    <strong><?= e($dish['name']) ?></strong>
                                    <?php if ($dish['is_vegetarian']): ?><span class="tag tag-veg">Veg</span><?php endif; ?>
                                    <?php if ($dish['is_spicy']): ?><span class="tag tag-spicy">Spicy</span><?php endif; ?>
                                </td>
                                <td><?= e($dish['category_name']) ?></td>
                                <td class="muted"><?= e($dish['package_names']) ?></td>
                                <td class="num"><?= (int) $dish['times_ordered'] ?></td>
                                <td>
                                    <form method="post" action="admin.php?view=menu">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_dish">
                                        <input type="hidden" name="dish_id" value="<?= (int) $dish['id'] ?>">
                                        <button class="toggle<?= $dish['is_active'] ? ' is-on' : '' ?>" type="submit"
                                                aria-label="<?= $dish['is_active'] ? 'Hide' : 'Show' ?> <?= e($dish['name']) ?>">
                                            <?= $dish['is_active'] ? 'Available' : 'Hidden' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form class="summary" method="post" action="admin.php?view=menu#dishes" id="addDish">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_dish">
                <h2>Add a dish</h2>
                <?php if ($addErrors): ?>
                    <div class="alert alert-error"><?php foreach ($addErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div>
                <?php endif; ?>
                <div class="field">
                    <label for="dish-name">Name</label>
                    <input type="text" id="dish-name" name="name" required minlength="3" maxlength="80" value="<?= e($newDish['name']) ?>">
                </div>
                <div class="field">
                    <label for="dish-category">Category</label>
                    <select id="dish-category" name="category_id" required>
                        <option value="">Choose...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>"<?= (string) $cat['id'] === $newDish['category_id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="dish-desc">Short description</label>
                    <input type="text" id="dish-desc" name="description" maxlength="200" value="<?= e($newDish['description']) ?>">
                </div>
                <fieldset class="field">
                    <legend>Offer in</legend>
                    <?php foreach ($packages as $pkg): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="packages[]" value="<?= (int) $pkg['id'] ?>" <?= in_array((int) $pkg['id'], $newDish['packages'], true) ? 'checked' : '' ?>>
                            <span><?= e($pkg['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="field">
                    <legend>Labels</legend>
                    <label class="checkbox"><input type="checkbox" name="is_vegetarian" value="1"><span>Vegetarian</span></label>
                    <label class="checkbox"><input type="checkbox" name="is_spicy" value="1"><span>Spicy</span></label>
                </fieldset>
                <button class="btn btn-primary btn-block" type="submit">Add dish</button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
