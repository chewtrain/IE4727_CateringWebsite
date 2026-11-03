<?php
// Staff view: all dishes (switch on/off, edit, delete) and the categories they belong to.
defined('IN_ADMIN') or exit;

$pdo = db();
$categoryError = '';

if (isPost()) {
    checkCsrf();
    $action = postValue('action');

    // Show or hide a dish on every menu.
    if ($action === 'toggle_dish') {
        $pdo->prepare('UPDATE dishes SET is_active = 1 - is_active WHERE id = ?')->execute([(int) postValue('dish_id')]);
        flash('success', 'Dish availability changed.');
        redirect('admin.php?view=dishes');
    }

    // Delete a dish. Package links go with it; past orders keep the copied dish name.
    if ($action === 'delete_dish') {
        $stmt = $pdo->prepare('DELETE FROM dishes WHERE id = ?');
        $stmt->execute([(int) postValue('dish_id')]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Dish deleted.' : 'That dish was already deleted.');
        redirect('admin.php?view=dishes');
    }

    // Add a category.
    if ($action === 'add_category') {
        $name = postValue('name');
        if (strlen($name) < 3 || strlen($name) > 40) {
            $categoryError = 'Category names must be 3 to 40 characters.';
        } else {
            try {
                $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();
                $pdo->prepare('INSERT INTO categories (name, sort_order) VALUES (?, ?)')->execute([$name, $next]);
                flash('success', 'Category ' . $name . ' was added.');
                redirect('admin.php?view=dishes#categories');
            } catch (PDOException $ex) {
                $categoryError = 'A category called ' . $name . ' already exists.';
            }
        }
    }

    // Rename a category or change its position.
    if ($action === 'update_category') {
        $name = postValue('name');
        $order = postValue('sort_order');
        if (strlen($name) < 3 || strlen($name) > 40 || !preg_match('/^-?\d{1,4}$/', $order)) {
            flash('error', 'Category names must be 3 to 40 characters and the order a whole number.');
        } else {
            try {
                $pdo->prepare('UPDATE categories SET name = ?, sort_order = ? WHERE id = ?')
                    ->execute([$name, (int) $order, (int) postValue('category_id')]);
                flash('success', 'Category saved.');
            } catch (PDOException $ex) {
                flash('error', 'Another category already has that name.');
            }
        }
        redirect('admin.php?view=dishes#categories');
    }

    // Delete an empty category (its "choose N" rules go with it).
    if ($action === 'delete_category') {
        $categoryId = (int) postValue('category_id');
        $count = $pdo->prepare('SELECT COUNT(*) FROM dishes WHERE category_id = ?');
        $count->execute([$categoryId]);
        if ($count->fetchColumn() > 0) {
            flash('error', 'Move or delete the dishes in this category first.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$categoryId]);
            flash('success', 'Category deleted.');
        }
        redirect('admin.php?view=dishes#categories');
    }
}

$categoryFilter = (int) getValue('category');

$sql = 'SELECT d.*, c.name AS category_name,
               GROUP_CONCAT(p.name ORDER BY p.sort_order SEPARATOR ", ") AS package_names,
               (SELECT COUNT(*) FROM order_item_dishes oid WHERE oid.dish_id = d.id) AS times_ordered
        FROM dishes d
        JOIN categories c ON c.id = d.category_id
        LEFT JOIN package_dishes pd ON pd.dish_id = d.id
        LEFT JOIN packages p ON p.id = pd.package_id'
     . ($categoryFilter ? ' WHERE d.category_id = ?' : '')
     . ' GROUP BY d.id ORDER BY c.sort_order, d.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($categoryFilter ? [$categoryFilter] : []);
$dishes = $stmt->fetchAll();

$categories = $pdo->query(
    'SELECT c.*, COUNT(d.id) AS dish_count
     FROM categories c LEFT JOIN dishes d ON d.category_id = c.id
     GROUP BY c.id ORDER BY c.sort_order, c.name'
)->fetchAll();

$pageTitle = 'Dishes | Staff';
$adminHeading = 'Dishes';
$adminIntro = 'Every dish on the menu. Hide a dish when it is sold out; it comes back with one click.';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">
        <div class="toolbar">
            <div class="chip-row">
                <a class="chip<?= $categoryFilter === 0 ? ' active' : '' ?>" href="admin.php?view=dishes">All</a>
                <?php foreach ($categories as $cat): ?>
                    <a class="chip<?= $categoryFilter === (int) $cat['id'] ? ' active' : '' ?>"
                       href="admin.php?view=dishes&amp;category=<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?> <span><?= (int) $cat['dish_count'] ?></span></a>
                <?php endforeach; ?>
            </div>
            <a class="btn btn-primary btn-small" href="admin.php?view=dish">New dish</a>
        </div>

        <div class="table-wrap">
            <table class="table admin-table">
                <thead>
                    <tr>
                        <th scope="col">Dish</th>
                        <th scope="col">Category</th>
                        <th scope="col">In packages</th>
                        <th scope="col" class="num">Ordered</th>
                        <th scope="col">Availability</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$dishes): ?><tr><td colspan="6" class="empty">No dishes in this category.</td></tr><?php endif; ?>
                    <?php foreach ($dishes as $dish): ?>
                        <tr class="<?= $dish['is_active'] ? '' : 'row-muted' ?>">
                            <td>
                                <strong><?= e($dish['name']) ?></strong>
                                <?php if ($dish['is_vegetarian']): ?><span class="tag tag-veg">Veg</span><?php endif; ?>
                                <?php if ($dish['is_spicy']): ?><span class="tag tag-spicy">Spicy</span><?php endif; ?>
                                <br><span class="muted"><?= e($dish['description']) ?></span>
                            </td>
                            <td><?= e($dish['category_name']) ?></td>
                            <td class="muted"><?= $dish['package_names'] ? e($dish['package_names']) : 'None' ?></td>
                            <td class="num"><?= (int) $dish['times_ordered'] ?></td>
                            <td>
                                <form method="post" action="admin.php?view=dishes">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle_dish">
                                    <input type="hidden" name="dish_id" value="<?= (int) $dish['id'] ?>">
                                    <button class="toggle<?= $dish['is_active'] ? ' is-on' : '' ?>" type="submit"
                                            aria-label="<?= $dish['is_active'] ? 'Hide' : 'Show' ?> <?= e($dish['name']) ?>">
                                        <?= $dish['is_active'] ? 'Available' : 'Hidden' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="row-actions">
                                <a href="admin.php?view=dish&amp;id=<?= (int) $dish['id'] ?>">Edit</a>
                                <form method="post" action="admin.php?view=dishes"
                                      data-confirm="Delete <?= e($dish['name']) ?>? It will be removed from every package.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_dish">
                                    <input type="hidden" name="dish_id" value="<?= (int) $dish['id'] ?>">
                                    <button class="link-button link-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="block-title section-gap" id="categories">Categories</h2>
        <div class="admin-split">
            <div class="table-wrap">
                <?php foreach ($categories as $cat): ?>
                    <form id="cat-<?= (int) $cat['id'] ?>" method="post" action="admin.php?view=dishes">
                        <?= csrfField() ?>
                        <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                    </form>
                <?php endforeach; ?>
                <table class="table admin-table">
                    <thead>
                        <tr><th scope="col">Name</th><th scope="col">Order</th><th scope="col" class="num">Dishes</th><th scope="col"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <?php $formId = 'cat-' . (int) $cat['id']; ?>
                            <tr>
                                <td><input form="<?= $formId ?>" type="text" name="name" required minlength="3" maxlength="40" value="<?= e($cat['name']) ?>" aria-label="Name of <?= e($cat['name']) ?>"></td>
                                <td><input class="input-small" form="<?= $formId ?>" type="number" name="sort_order" step="1" value="<?= (int) $cat['sort_order'] ?>" aria-label="Order of <?= e($cat['name']) ?>"></td>
                                <td class="num"><?= (int) $cat['dish_count'] ?></td>
                                <td class="row-actions">
                                    <button class="btn btn-small btn-secondary" form="<?= $formId ?>" type="submit" name="action" value="update_category">Save</button>
                                    <?php if ((int) $cat['dish_count'] === 0): ?>
                                        <button class="link-button link-danger" form="<?= $formId ?>" type="submit" name="action" value="delete_category"
                                                data-confirm="Delete the <?= e($cat['name']) ?> category?">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form class="summary" method="post" action="admin.php?view=dishes#categories">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_category">
                <h2>Add a category</h2>
                <?php if ($categoryError): ?><p class="alert alert-error"><?= e($categoryError) ?></p><?php endif; ?>
                <div class="field">
                    <label for="new-category">Name</label>
                    <input type="text" id="new-category" name="name" required minlength="3" maxlength="40" placeholder="e.g. Soups">
                </div>
                <button class="btn btn-primary btn-block" type="submit">Add category</button>
                <p class="field-hint">Categories with no dishes can be deleted.</p>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
