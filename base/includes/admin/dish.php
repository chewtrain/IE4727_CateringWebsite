<?php
// Staff view: create a dish (no id) or edit one (?id=...), including which packages offer it.
defined('IN_ADMIN') or exit;

$pdo = db();
$dishId = (int) getValue('id');
$isNew = $dishId === 0;

$dish = ['name' => '', 'category_id' => '', 'description' => '', 'is_vegetarian' => 0, 'is_spicy' => 0, 'is_active' => 1];
$inPackages = [];

if (!$isNew) {
    $stmt = $pdo->prepare('SELECT * FROM dishes WHERE id = ?');
    $stmt->execute([$dishId]);
    $dish = $stmt->fetch();
    if (!$dish) {
        flash('error', 'That dish does not exist.');
        redirect('admin.php?view=dishes');
    }
    $stmt = $pdo->prepare('SELECT package_id FROM package_dishes WHERE dish_id = ?');
    $stmt->execute([$dishId]);
    $inPackages = array_map('intval', $stmt->fetchAll(PDO::FEefTCH_COLUMN));
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$packages = $pdo->query('SELECT id, name, is_active FROM packages ORDER BY sort_order, name')->fetchAll();
$errors = [];

if (isPost()) {
    checkCsrf();
    $dish['name'] = postValue('name');
    $dish['category_id'] = postValue('category_id');
    $dish['description'] = postValue('description');
    $dish['is_vegetarian'] = empty($_POST['is_vegetarian']) ? 0 : 1;
    $dish['is_spicy'] = empty($_POST['is_spicy']) ? 0 : 1;
    $dish['is_active'] = empty($_POST['is_active']) ? 0 : 1;
    $inPackages = isset($_POST['packages']) && is_array($_POST['packages'])
        ? array_values(array_intersect(array_map('intval', $_POST['packages']), array_map('intval', array_column($packages, 'id'))))
        : [];

    if (strlen($dish['name']) < 3 || strlen($dish['name']) > 80) {
        $errors['name'] = 'Dish name must be 3 to 80 characters.';
    }
    if (!in_array((int) $dish['category_id'], array_map('intval', array_column($categories, 'id')), true)) {
        $errors['category_id'] = 'Choose a category.';
    }
    if (strlen($dish['description']) > 200) {
        $errors['description'] = 'Keep the description under 200 characters.';
    }

    if (!$errors) {
        $values = [(int) $dish['category_id'], $dish['name'], $dish['description'], $dish['is_vegetarian'], $dish['is_spicy'], $dish['is_active']];
        $pdo->beginTransaction();
        if ($isNew) {
            $pdo->prepare('INSERT INTO dishes (category_id, name, description, is_vegetarian, is_spicy, is_active) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute($values);
            $dishId = (int) $pdo->lastInsertId();
        } else {
            $values[] = $dishId;
            $pdo->prepare('UPDATE dishes SET category_id = ?, name = ?, description = ?, is_vegetarian = ?, is_spicy = ?, is_active = ? WHERE id = ?')
                ->execute($values);
        }
        $pdo->prepare('DELETE FROM package_dishes WHERE dish_id = ?')->execute([$dishId]);
        $link = $pdo->prepare('INSERT INTO package_dishes (package_id, dish_id) VALUES (?, ?)');
        foreach ($inPackages as $packageId) {
            $link->execute([$packageId, $dishId]);
        }
        $pdo->commit();

        flash('success', $dish['name'] . ($isNew ? ' was added.' : ' was saved.'));
        redirect('admin.php?view=dishes');
    }
}

$pageTitle = ($isNew ? 'New Dish' : 'Edit ' . $dish['name']) . ' | Staff';
$adminHeading = $isNew ? 'New dish' : 'Edit ' . $dish['name'];
$adminCrumb = $isNew ? 'New' : $dish['name'];
$adminIntro = 'A dish appears on the menu of every package ticked below.';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container narrow">
        <form class="form-card" method="post" action="admin.php?view=dish<?= $isNew ? '' : '&amp;id=' . (int) $dishId ?>">
            <?= csrfField() ?>
            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required minlength="3" maxlength="80" value="<?= e($dish['name']) ?>">
                <?= adminFieldError($errors, 'name') ?>
            </div>
            <div class="field<?= isset($errors['category_id']) ? ' has-error' : '' ?>">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"<?= (string) $cat['id'] === (string) $dish['category_id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= adminFieldError($errors, 'category_id') ?>
            </div>
            <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>">
                <label for="description">Short description <span class="muted">(optional)</span></label>
                <input type="text" id="description" name="description" maxlength="200" value="<?= e($dish['description']) ?>">
                <?= adminFieldError($errors, 'description') ?>
            </div>
            <fieldset class="field">
                <legend>Offered in</legend>
                <?php foreach ($packages as $pkg): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="packages[]" value="<?= (int) $pkg['id'] ?>" <?= in_array((int) $pkg['id'], $inPackages, true) ? 'checked' : '' ?>>
                        <span><?= e($pkg['name']) ?><?= $pkg['is_active'] ? '' : ' (hidden)' ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="field">
                <legend>Labels</legend>
                <label class="checkbox"><input type="checkbox" name="is_vegetarian" value="1" <?= $dish['is_vegetarian'] ? 'checked' : '' ?>><span>Vegetarian</span></label>
                <label class="checkbox"><input type="checkbox" name="is_spicy" value="1" <?= $dish['is_spicy'] ? 'checked' : '' ?>><span>Spicy</span></label>
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= $dish['is_active'] ? 'checked' : '' ?>><span>Available to order</span></label>
            </fieldset>
            <div class="form-actions">
                <a href="admin.php?view=dishes">Cancel</a>
                <button class="btn btn-primary" type="submit"><?= $isNew ? 'Add dish' : 'Save changes' ?></button>
            </div>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
