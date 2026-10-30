<?php
require_once __DIR__ . '/includes/config.php';

$packageId = (int) getValue('id');
$package = getPackage($packageId);
if (!$package) {
    http_response_code(404);
    $pageTitle = 'Package Not Found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="page-head"><div class="container"><h1>Package not found.</h1>'
       . '<p class="lead">It may no longer be available. <a href="buffets.php">See all buffets</a>.</p></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$menu = getPackageMenu($packageId);
$errors = [];
$guests = (int) $package['min_pax'];
$chosen = [];

// Add to cart.
if (isPost()) {
    checkCsrf();

    $guestsInput = postValue('guests');
    $chosen = isset($_POST['dishes']) && is_array($_POST['dishes']) ? array_map('intval', $_POST['dishes']) : [];
    $chosen = array_values(array_unique($chosen));

    if (!ctype_digit($guestsInput)) {
        $errors[] = 'Enter the number of guests as a whole number.';
    } else {
        $guests = (int) $guestsInput;
        if ($guests < $package['min_pax'] || $guests > MAX_GUESTS) {
            $errors[] = 'Guests must be between ' . $package['min_pax'] . ' and ' . MAX_GUESTS . ' for this package.';
        }
    }
    $errors = array_merge($errors, validateDishSelection($menu, $chosen));

    if (!$errors) {
        cartAdd($packageId, $guests, $chosen);
        flash('success', $package['name'] . ' for ' . $guests . ' guests was added to your cart.');
        redirect('cart.php');
    }
}

$totalPicks = 0;
$unavailable = [];   // categories without enough dishes switched on
foreach ($menu as $category) {
    $totalPicks += $category['choose'];
    if (count($category['dishes']) < $category['choose']) {
        $unavailable[] = $category['name'];
    }
}

$pageTitle = $package['name'];
$pageScripts = ['js/package.js'];
require __DIR__ . '/includes/header.php';
?>

<section class="package-hero">
    <div class="container package-hero-grid">
        <img src="<?= e($package['image']) ?>" alt="<?= e($package['image_alt']) ?>">
        <div>
            <p class="eyebrow"><a href="buffets.php">Buffets</a> / <?= e($package['name']) ?></p>
            <h1><?= e($package['name']) ?></h1>
            <p class="lead"><?= e($package['tagline']) ?> <?= e($package['description']) ?></p>
            <ul class="package-facts">
                <li><strong><?= money($package['price_per_pax']) ?></strong> per guest</li>
                <li>Min. <?= (int) $package['min_pax'] ?> guests</li>
                <li>Choose <?= $totalPicks ?> dishes</li>
            </ul>
        </div>
    </div>
</section>

<section class="section-tight section-alt">
    <form class="container picker" id="pickerForm" method="post"
          action="package.php?id=<?= (int) $packageId ?>" novalidate
          data-price="<?= e($package['price_per_pax']) ?>"
          data-free-min="<?= e(FREE_DELIVERY_MIN) ?>" data-fee="<?= e(DELIVERY_FEE) ?>">
        <?= csrfField() ?>

        <div class="picker-menu">
            <?php if ($unavailable): ?>
                <p class="alert alert-error">
                    This package cannot be ordered right now because some <?= e(strtolower(implode(', ', $unavailable))) ?>
                    are sold out. Please choose <a href="buffets.php">another package</a> or call us on <?= e(CONTACT_PHONE) ?>.
                </p>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert" id="serverErrors">
                    <p><strong>Please fix the following:</strong></p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php foreach ($menu as $categoryId => $category): ?>
                <fieldset class="dish-group" data-choose="<?= $category['choose'] ?>" id="cat-<?= (int) $categoryId ?>">
                    <legend>
                        <span class="dish-group-name"><?= e($category['name']) ?></span>
                        <span class="dish-group-rule">
                            Choose <?= $category['choose'] ?>
                            <span class="dish-counter" aria-live="polite">(<span class="dish-count">0</span> of <?= $category['choose'] ?>)</span>
                        </span>
                    </legend>
                    <div class="dish-grid">
                        <?php foreach ($category['dishes'] as $dish): ?>
                            <label class="dish-option">
                                <input type="checkbox" name="dishes[]" value="<?= (int) $dish['dish_id'] ?>"
                                       <?= in_array((int) $dish['dish_id'], $chosen, true) ? 'checked' : '' ?>>
                                <span class="dish-card">
                                    <span class="dish-name"><?= e($dish['dish_name']) ?></span>
                                    <span class="dish-desc"><?= e($dish['description']) ?></span>
                                    <span class="dish-tags">
                                        <?php if ($dish['is_vegetarian']): ?><span class="tag tag-veg">Vegetarian</span><?php endif; ?>
                                        <?php if ($dish['is_spicy']): ?><span class="tag tag-spicy">Spicy</span><?php endif; ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="field-error" hidden></p>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <aside class="summary picker-summary">
            <h2>Your buffet</h2>
            <div class="field">
                <label for="guests">Number of guests</label>
                <input type="number" id="guests" name="guests" inputmode="numeric"
                       min="<?= (int) $package['min_pax'] ?>" max="<?= MAX_GUESTS ?>" step="1" required
                       value="<?= (int) $guests ?>">
                <p class="field-hint">Between <?= (int) $package['min_pax'] ?> and <?= MAX_GUESTS ?>.</p>
                <p class="field-error" hidden></p>
            </div>

            <dl class="summary-lines">
                <div><dt>Price per guest</dt><dd><?= money($package['price_per_pax']) ?></dd></div>
                <div><dt>Dishes chosen</dt><dd><span id="pickedTotal">0</span> of <?= $totalPicks ?></dd></div>
                <div class="summary-total"><dt>Estimated total</dt><dd id="estimate"><?= money($package['price_per_pax'] * $guests) ?></dd></div>
            </dl>
            <p class="field-hint" id="deliveryHint">Delivery and set-up are added at checkout.</p>

            <button class="btn btn-primary btn-block" type="submit"<?= $unavailable ? ' disabled' : '' ?>>Add to cart</button>
        </aside>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
