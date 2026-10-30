<?php
require_once __DIR__ . '/includes/config.php';

// Update guest numbers or remove a line, then reload the page (Post/Redirect/Get).
if (isPost()) {
    checkCsrf();
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

    if (isset($_POST['remove'])) {
        $index = (int) $_POST['remove'];
        if (isset($cart[$index])) {
            unset($cart[$index]);
            $_SESSION['cart'] = array_values($cart);
            flash('success', 'The package was removed from your cart.');
        }
        redirect('cart.php');
    }

    $guestInputs = isset($_POST['guests']) && is_array($_POST['guests']) ? $_POST['guests'] : [];
    $problems = 0;
    foreach ($guestInputs as $index => $value) {
        $index = (int) $index;
        if (!isset($cart[$index])) {
            continue;
        }
        $package = getPackage($cart[$index]['package_id']);
        $value = is_string($value) ? trim($value) : '';
        if ($package && ctype_digit($value) && (int) $value >= $package['min_pax'] && (int) $value <= MAX_GUESTS) {
            $cart[$index]['guests'] = (int) $value;
        } else {
            $problems++;
        }
    }
    $_SESSION['cart'] = $cart;

    if ($problems > 0) {
        flash('error', 'Some guest numbers were not changed. Each package has a minimum, and the maximum is ' . MAX_GUESTS . '.');
    } else {
        flash('success', 'Your cart was updated.');
    }
    redirect('cart.php');
}

$cart = cartDetails();

$pageTitle = 'Your Cart';
$pageScripts = ['js/cart.js'];
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div class="container">
        <h1>Your cart.</h1>
        <?php if (!$cart['lines']): ?>
            <p class="lead">Your cart is empty. Start with one of our buffet packages.</p>
            <p class="page-head-actions"><a class="btn btn-primary" href="buffets.php">Browse buffets</a></p>
        <?php else: ?>
            <p class="lead">Check your packages and guest numbers, then continue to checkout.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($cart['lines']): ?>
<section class="section-tight">
    <form class="container" id="cartForm" method="post" action="cart.php"
          data-free-min="<?= e(FREE_DELIVERY_MIN) ?>" data-fee="<?= e(DELIVERY_FEE) ?>">
        <?= csrfField() ?>

        <?php if (!$cart['valid']): ?>
            <p class="alert alert-error">Part of your cart is no longer on our menu. Please remove the marked items before checking out.</p>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="table cart-table">
                <caption class="sr-only">Packages in your cart</caption>
                <thead>
                    <tr>
                        <th scope="col">Package</th>
                        <th scope="col">Guests</th>
                        <th scope="col" class="num">Per guest</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart['lines'] as $index => $line): ?>
                        <?php if ($line['missing']): ?>
                            <tr class="row-error">
                                <td colspan="4">This package is no longer available.</td>
                                <td><button class="link-button" type="submit" name="remove" value="<?= (int) $index ?>">Remove</button></td>
                            </tr>
                            <?php continue; ?>
                        <?php endif; ?>
                        <?php $pkg = $line['package']; ?>
                        <tr data-price="<?= e($pkg['price_per_pax']) ?>">
                            <td>
                                <div class="cart-item">
                                    <img src="<?= e($pkg['image']) ?>" alt="" width="96" height="72">
                                    <div>
                                        <a class="cart-item-name" href="package.php?id=<?= (int) $pkg['id'] ?>"><?= e($pkg['name']) ?></a>
                                        <details class="cart-dishes">
                                            <summary><?= count($line['dishes']) ?> dishes chosen</summary>
                                            <ul>
                                                <?php foreach ($line['dishes'] as $dish): ?>
                                                    <li><?= e($dish['name']) ?> <span class="muted">(<?= e($dish['category_name']) ?>)</span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <label class="sr-only" for="guests-<?= (int) $index ?>">Guests for <?= e($pkg['name']) ?></label>
                                <input class="guests-input" type="number" id="guests-<?= (int) $index ?>"
                                       name="guests[<?= (int) $index ?>]" value="<?= (int) $line['guests'] ?>"
                                       min="<?= (int) $pkg['min_pax'] ?>" max="<?= MAX_GUESTS ?>" step="1" required>
                                <span class="field-hint">Min. <?= (int) $pkg['min_pax'] ?></span>
                            </td>
                            <td class="num"><?= money($pkg['price_per_pax']) ?></td>
                            <td class="num line-total"><?= money($line['line_total']) ?></td>
                            <td><button class="link-button" type="submit" name="remove" value="<?= (int) $index ?>">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-footer">
            <div>
                <button class="btn btn-secondary" type="submit" id="updateCart">Update guest numbers</button>
                <p class="field-hint"><a href="buffets.php">Add another package</a></p>
            </div>

            <dl class="summary-lines cart-totals">
                <div><dt>Subtotal</dt><dd id="cartSubtotal"><?= money($cart['subtotal']) ?></dd></div>
                <div><dt>Delivery and set-up</dt><dd id="cartDelivery"><?= $cart['delivery'] > 0 ? money($cart['delivery']) : 'Free' ?></dd></div>
                <div class="summary-total"><dt>Total</dt><dd id="cartTotal"><?= money($cart['total']) ?></dd></div>
                <?php if ($cart['delivery'] > 0): ?>
                    <p class="field-hint">Spend <?= money(FREE_DELIVERY_MIN - $cart['subtotal']) ?> more for free delivery.</p>
                <?php endif; ?>
                <?php if ($cart['valid']): ?>
                    <a class="btn btn-primary btn-block" href="checkout.php">Check out</a>
                <?php endif; ?>
            </dl>
        </div>
    </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
