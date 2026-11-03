<?php
// Staff view: create a package (no id) or edit one (?id=...): details, photo,
// how many dishes to pick per category, and which dishes it offers.
defined('IN_ADMIN') or exit;

$pdo = db();
$packageId = (int) getValue('id');
$isNew = $packageId === 0;

$package = [
    'name' => '', 'tagline' => '', 'description' => '', 'price_per_pax' => '', 'min_pax' => '20',
    'image' => '', 'image_alt' => '', 'is_featured' => 0, 'is_active' => 0, 'sort_order' => '0',
];
$rules = [];      // category_id => choose_count
$offered = [];    // dish ids

if (!$isNew) {
    $stmt = $pdo->prepare('SELECT * FROM packages WHERE id = ?');
    $stmt->execute([$packageId]);
    $package = $stmt->fetch();
    if (!$package) {
        flash('error', 'That package does not exist.');
        redirect('admin.php?view=packages');
    }
    $stmt = $pdo->prepare('SELECT category_id, choose_count FROM package_rules WHERE package_id = ?');
    $stmt->execute([$packageId]);
    foreach ($stmt as $row) {
        $rules[(int) $row['category_id']] = (int) $row['choose_count'];
    }
    $stmt = $pdo->prepare('SELECT dish_id FROM package_dishes WHERE package_id = ?');
    $stmt->execute([$packageId]);
    $offered = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
$dishes = $pdo->query('SELECT id, category_id, name, is_active FROM dishes ORDER BY name')->fetchAll();
$dishesByCategory = [];
foreach ($dishes as $dish) {
    $dishesByCategory[$dish['category_id']][] = $dish;
}

// Photos already in the images folder, for the photo picker.
$images = [];
foreach (scandir(__DIR__ . '/../../images') as $file) {
    if (preg_match('/\.(jpe?g|png|webp)$/i', $file)) {
        $images[] = 'images/' . $file;
    }
}

$errors = [];

if (isPost()) {
    checkCsrf();
    foreach (['name', 'tagline', 'description', 'price_per_pax', 'min_pax', 'image', 'image_alt', 'sort_order'] as $field) {
        $package[$field] = postValue($field);
    }
    $package['is_featured'] = empty($_POST['is_featured']) ? 0 : 1;
    $package['is_active'] = empty($_POST['is_active']) ? 0 : 1;

    $rules = [];
    $ruleInput = isset($_POST['rules']) && is_array($_POST['rules']) ? $_POST['rules'] : [];
    foreach ($categories as $cat) {
        $value = isset($ruleInput[$cat['id']]) && is_string($ruleInput[$cat['id']]) ? trim($ruleInput[$cat['id']]) : '0';
        if (!ctype_digit($value) || (int) $value > 10) {
            $errors['rules'] = 'Each category takes a whole number from 0 to 10.';
        } elseif ((int) $value > 0) {
            $rules[(int) $cat['id']] = (int) $value;
        }
    }
    $validDishIds = array_map('intval', array_column($dishes, 'id'));
    $offered = isset($_POST['dishes']) && is_array($_POST['dishes'])
        ? array_values(array_intersect(array_map('intval', $_POST['dishes']), $validDishIds))
        : [];

    $len = function ($value) {
        return strlen($value);
    };
    if ($len($package['name']) < 3 || $len($package['name']) > 60) {
        $errors['name'] = 'Name must be 3 to 60 characters.';
    } else {
        $dupe = $pdo->prepare('SELECT COUNT(*) FROM packages WHERE name = ? AND id <> ?');
        $dupe->execute([$package['name'], $packageId]);
        if ($dupe->fetchColumn()) {
            $errors['name'] = 'Another package already has this name.';
        }
    }
    if ($len($package['tagline']) < 3 || $len($package['tagline']) > 120) {
        $errors['tagline'] = 'Tagline must be 3 to 120 characters.';
    }
    if ($len($package['description']) < 10 || $len($package['description']) > 500) {
        $errors['description'] = 'Description must be 10 to 500 characters.';
    }
    if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $package['price_per_pax']) || (float) $package['price_per_pax'] <= 0) {
        $errors['price_per_pax'] = 'Enter a price like 16.80.';
    }
    if (!ctype_digit($package['min_pax']) || (int) $package['min_pax'] < 10 || (int) $package['min_pax'] > MAX_GUESTS) {
        $errors['min_pax'] = 'Minimum guests must be between 10 and ' . MAX_GUESTS . '.';
    }
    if (!in_array($package['image'], $images, true)) {
        $errors['image'] = 'Choose a photo.';
    }
    if ($len($package['image_alt']) < 5 || $len($package['image_alt']) > 160) {
        $errors['image_alt'] = 'Describe the photo in 5 to 160 characters (read aloud by screen readers).';
    }
    if (!preg_match('/^-?\d{1,4}$/', $package['sort_order'])) {
        $errors['sort_order'] = 'Enter a whole number.';
    }
    if (!$rules && !isset($errors['rules'])) {
        $errors['rules'] = 'Customers must choose from at least one category.';
    }

    if (!$errors) {
        $values = [
            $package['name'], $package['tagline'], $package['description'], $package['price_per_pax'],
            (int) $package['min_pax'], $package['image'], $package['image_alt'],
            $package['is_featured'], $package['is_active'], (int) $package['sort_order'],
        ];
        $pdo->beginTransaction();
        if ($isNew) {
            $pdo->prepare(
                'INSERT INTO packages (name, tagline, description, price_per_pax, min_pax, image, image_alt, is_featured, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute($values);
            $packageId = (int) $pdo->lastInsertId();
        } else {
            $values[] = $packageId;
            $pdo->prepare(
                'UPDATE packages SET name = ?, tagline = ?, description = ?, price_per_pax = ?, min_pax = ?,
                        image = ?, image_alt = ?, is_featured = ?, is_active = ?, sort_order = ?
                 WHERE id = ?'
            )->execute($values);
        }

        // Replace the rules and dish links with what was submitted.
        $pdo->prepare('DELETE FROM package_rules WHERE package_id = ?')->execute([$packageId]);
        $insertRule = $pdo->prepare('INSERT INTO package_rules (package_id, category_id, choose_count) VALUES (?, ?, ?)');
        foreach ($rules as $categoryId => $count) {
            $insertRule->execute([$packageId, $categoryId, $count]);
        }
        $pdo->prepare('DELETE FROM package_dishes WHERE package_id = ?')->execute([$packageId]);
        $insertDish = $pdo->prepare('INSERT INTO package_dishes (package_id, dish_id) VALUES (?, ?)');
        foreach ($offered as $dishId) {
            $insertDish->execute([$packageId, $dishId]);
        }
        $pdo->commit();

        flash('success', $package['name'] . ($isNew ? ' was created.' : ' was saved.'));
        redirect('admin.php?view=packages');
    }
}

$pageTitle = ($isNew ? 'New Package' : 'Edit ' . $package['name']) . ' | Staff';
$adminHeading = $isNew ? 'New package' : 'Edit ' . $package['name'];
$adminCrumb = $isNew ? 'New' : $package['name'];
$adminIntro = 'New packages start hidden. Tick "On sale" once the menu is ready.';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <form class="container admin-form" method="post" action="admin.php?view=package<?= $isNew ? '' : '&amp;id=' . (int) $packageId ?>">
        <?= csrfField() ?>
        <?php if ($errors): ?>
            <p class="alert alert-error" role="alert">Please fix the highlighted fields.</p>
        <?php endif; ?>

        <div class="admin-form-grid">
            <div class="form-card">
                <h2>Details</h2>
                <div class="form-grid">
                    <div class="field field-full<?= isset($errors['name']) ? ' has-error' : '' ?>">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required minlength="3" maxlength="60" value="<?= e($package['name']) ?>">
                        <?= adminFieldError($errors, 'name') ?>
                    </div>
                    <div class="field field-full<?= isset($errors['tagline']) ? ' has-error' : '' ?>">
                        <label for="tagline">Tagline</label>
                        <input type="text" id="tagline" name="tagline" required maxlength="120" value="<?= e($package['tagline']) ?>">
                        <?= adminFieldError($errors, 'tagline') ?>
                    </div>
                    <div class="field field-full<?= isset($errors['description']) ? ' has-error' : '' ?>">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" required maxlength="500"><?= e($package['description']) ?></textarea>
                        <?= adminFieldError($errors, 'description') ?>
                    </div>
                    <div class="field<?= isset($errors['price_per_pax']) ? ' has-error' : '' ?>">
                        <label for="price_per_pax">Price per guest ($)</label>
                        <input type="number" id="price_per_pax" name="price_per_pax" required min="0.01" max="999" step="0.01" value="<?= e($package['price_per_pax']) ?>">
                        <?= adminFieldError($errors, 'price_per_pax') ?>
                    </div>
                    <div class="field<?= isset($errors['min_pax']) ? ' has-error' : '' ?>">
                        <label for="min_pax">Minimum guests</label>
                        <input type="number" id="min_pax" name="min_pax" required min="10" max="<?= MAX_GUESTS ?>" step="1" value="<?= e($package['min_pax']) ?>">
                        <?= adminFieldError($errors, 'min_pax') ?>
                    </div>
                    <div class="field<?= isset($errors['sort_order']) ? ' has-error' : '' ?>">
                        <label for="sort_order">Display order</label>
                        <input type="number" id="sort_order" name="sort_order" step="1" value="<?= e($package['sort_order']) ?>">
                        <p class="field-hint">Lower numbers are listed first.</p>
                        <?= adminFieldError($errors, 'sort_order') ?>
                    </div>
                    <fieldset class="field">
                        <legend>Visibility</legend>
                        <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= $package['is_active'] ? 'checked' : '' ?>><span>On sale</span></label>
                        <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= $package['is_featured'] ? 'checked' : '' ?>><span>Show on homepage</span></label>
                    </fieldset>
                </div>

                <h2>Photo</h2>
                <fieldset class="field<?= isset($errors['image']) ? ' has-error' : '' ?>">
                    <legend class="sr-only">Package photo</legend>
                    <div class="photo-picker">
                        <?php foreach ($images as $image): ?>
                            <label class="photo-option">
                                <input type="radio" name="image" value="<?= e($image) ?>" required <?= $package['image'] === $image ? 'checked' : '' ?>>
                                <img src="<?= e($image) ?>" alt="<?= e(basename($image)) ?>" loading="lazy">
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?= adminFieldError($errors, 'image') ?>
                </fieldset>
                <div class="field<?= isset($errors['image_alt']) ? ' has-error' : '' ?>">
                    <label for="image_alt">Photo description</label>
                    <input type="text" id="image_alt" name="image_alt" required maxlength="160"
                           placeholder="e.g. Bamboo steamers of har gow and siew mai" value="<?= e($package['image_alt']) ?>">
                    <?= adminFieldError($errors, 'image_alt') ?>
                </div>
            </div>

            <div class="form-card">
                <h2>Menu</h2>
                <p class="field-hint">For each category, set how many dishes the customer picks (0 leaves it out), then tick the dishes on offer.</p>
                <?= adminFieldError($errors, 'rules') ?>
                <?php foreach ($categories as $cat): ?>
                    <?php $catId = (int) $cat['id']; ?>
                    <fieldset class="rule-group">
                        <legend>
                            <span><?= e($cat['name']) ?></span>
                            <label class="rule-count">
                                Choose
                                <input class="input-small" type="number" name="rules[<?= $catId ?>]" min="0" max="10" step="1"
                                       value="<?= isset($rules[$catId]) ? $rules[$catId] : 0 ?>" aria-label="Dishes to choose from <?= e($cat['name']) ?>">
                            </label>
                        </legend>
                        <div class="rule-dishes">
                            <?php foreach (isset($dishesByCategory[$catId]) ? $dishesByCategory[$catId] : [] as $dish): ?>
                                <label class="checkbox<?= $dish['is_active'] ? '' : ' muted' ?>">
                                    <input type="checkbox" name="dishes[]" value="<?= (int) $dish['id'] ?>" <?= in_array((int) $dish['id'], $offered, true) ? 'checked' : '' ?>>
                                    <span><?= e($dish['name']) ?><?= $dish['is_active'] ? '' : ' (hidden)' ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($dishesByCategory[$catId])): ?><p class="muted">No dishes in this category yet.</p><?php endif; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="admin.php?view=packages">Cancel</a>
            <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create package' : 'Save changes' ?></button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
