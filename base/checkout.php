<?php
require_once __DIR__ . '/includes/config.php';

$cart = cartDetails();
if (!$cart['lines'] || !$cart['valid']) {
    redirect('cart.php');
}

$fields = ['customer_name', 'email', 'email_confirm', 'phone', 'address', 'postal_code', 'event_date', 'time_slot', 'notes'];
$values = array_fill_keys($fields, '');
$errors = [];

if (isPost()) {
    checkCsrf();
    foreach ($fields as $field) {
        $values[$field] = postValue($field);
    }
    $values['email'] = strtolower($values['email']);
    $values['email_confirm'] = strtolower($values['email_confirm']);
    $values['phone'] = str_replace(' ', '', $values['phone']);

    // Server-side validation: the same rules as the browser, which can be bypassed.
    if (!validName($values['customer_name'])) {
        $errors['customer_name'] = 'Enter your full name using letters only.';
    }
    if (!validEmail($values['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ($values['email'] !== $values['email_confirm']) {
        $errors['email_confirm'] = 'The two email addresses do not match.';
    }
    if (!validPhone($values['phone'])) {
        $errors['phone'] = 'Enter an 8-digit Singapore number starting with 6, 8 or 9.';
    }
    if (strlen($values['address']) < 5 || strlen($values['address']) > 200) {
        $errors['address'] = 'Enter the delivery address (block, street and unit).';
    }
    if (!validPostalCode($values['postal_code'])) {
        $errors['postal_code'] = 'Enter a valid 6-digit Singapore postal code.';
    }
    if (!validEventDate($values['event_date'])) {
        $errors['event_date'] = 'Choose a date between ' . earliestDeliveryDate()->format('j M Y')
            . ' and ' . latestDeliveryDate()->format('j M Y') . '.';
    }
    if (!in_array($values['time_slot'], $timeSlots, true)) {
        $errors['time_slot'] = 'Choose a delivery time slot.';
    }
    if (strlen($values['notes']) > 500) {
        $errors['notes'] = 'Keep notes under 500 characters.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Please accept the delivery terms.';
    }

    if (!$errors) {
        $pdo = db();
        $ref = generateOrderRef();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO orders (order_ref, customer_name, email, phone, address, postal_code,
                                     event_date, time_slot, notes, subtotal, delivery_fee, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $ref, $values['customer_name'], $values['email'], $values['phone'], $values['address'],
                $values['postal_code'], $values['event_date'], $values['time_slot'], $values['notes'],
                $cart['subtotal'], $cart['delivery'], $cart['total'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, package_id, package_name, price_per_pax, guests, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $dishStmt = $pdo->prepare(
                'INSERT INTO order_item_dishes (order_item_id, dish_id, dish_name, category_name) VALUES (?, ?, ?, ?)'
            );
            foreach ($cart['lines'] as $line) {
                $pkg = $line['package'];
                $itemStmt->execute([$orderId, $pkg['id'], $pkg['name'], $pkg['price_per_pax'], $line['guests'], $line['line_total']]);
                $itemId = (int) $pdo->lastInsertId();
                foreach ($line['dishes'] as $dish) {
                    $dishStmt->execute([$itemId, $dish['id'], $dish['name'], $dish['category_name']]);
                }
            }

            $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)')
                ->execute([$orderId, 'pending_payment', 'Order placed']);
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        rememberOrder($ref);
        redirect('payment.php?ref=' . urlencode($ref));
    }
}

$earliest = earliestDeliveryDate();
$latest = latestDeliveryDate();

$pageTitle = 'Checkout';
$pageScripts = ['js/forms.js'];
require __DIR__ . '/includes/header.php';

// Print a field's error message, if any.
function fieldError($errors, $field)
{
    if (isset($errors[$field])) {
        return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
    }
    return '<p class="field-error" id="' . e($field) . '-error" hidden></p>';
}
?>

<section class="page-head page-head-split">
    <div class="container split-grid">
        <div class="split-text">
            <h1>Checkout.</h1>
            <p class="lead">No account needed. We'll email your confirmation and tracking link.</p>
        </div>
        <img src="images/occasion-corporate.jpg" alt="A buffet table with chafing dishes set up at an outdoor event">
    </div>
</section>

<section class="section-tight">
    <div class="container checkout-grid">
        <form class="form-card" id="checkoutForm" method="post" action="checkout.php" novalidate
              data-validate data-earliest="<?= e($earliest->format('Y-m-d')) ?>" data-latest="<?= e($latest->format('Y-m-d')) ?>">
            <?= csrfField() ?>

            <?php if ($errors): ?>
                <p class="alert alert-error" role="alert">Please correct the <?= count($errors) ?> highlighted field(s).</p>
            <?php endif; ?>

            <h2>Contact details</h2>
            <div class="form-grid">
                <div class="field field-full<?= isset($errors['customer_name']) ? ' has-error' : '' ?>">
                    <label for="customer_name">Full name</label>
                    <input type="text" id="customer_name" name="customer_name" autocomplete="name" required
                           maxlength="60" data-pattern-message="Use letters, spaces, apostrophes and hyphens only." pattern="[A-Za-z][A-Za-z .'\/\-]{1,59}" value="<?= e($values['customer_name']) ?>">
                    <?= fieldError($errors, 'customer_name') ?>
                </div>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email" required maxlength="120"
                           value="<?= e($values['email']) ?>">
                    <?= fieldError($errors, 'email') ?>
                </div>
                <div class="field<?= isset($errors['email_confirm']) ? ' has-error' : '' ?>">
                    <label for="email_confirm">Confirm email</label>
                    <input type="email" id="email_confirm" name="email_confirm" autocomplete="email" required maxlength="120"
                           data-match="email" value="<?= e($values['email_confirm']) ?>">
                    <?= fieldError($errors, 'email_confirm') ?>
                </div>
                <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                    <label for="phone">Mobile number</label>
                    <input type="tel" id="phone" name="phone" autocomplete="tel-national" required inputmode="numeric"
                           maxlength="9" pattern="[689]\d{3} ?\d{4}" placeholder="9123 4567" value="<?= e($values['phone']) ?>">
                    <p class="field-hint">For the driver on the day.</p>
                    <?= fieldError($errors, 'phone') ?>
                </div>
            </div>

            <h2>Delivery</h2>
            <div class="form-grid">
                <div class="field field-full<?= isset($errors['address']) ? ' has-error' : '' ?>">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" autocomplete="street-address" required
                           minlength="5" maxlength="200" placeholder="Block, street, unit number" value="<?= e($values['address']) ?>">
                    <?= fieldError($errors, 'address') ?>
                </div>
                <div class="field<?= isset($errors['postal_code']) ? ' has-error' : '' ?>">
                    <label for="postal_code">Postal code</label>
                    <input type="text" id="postal_code" name="postal_code" autocomplete="postal-code" required
                           inputmode="numeric" maxlength="6" pattern="\d{6}" data-postal value="<?= e($values['postal_code']) ?>">
                    <?= fieldError($errors, 'postal_code') ?>
                </div>
                <div class="field<?= isset($errors['event_date']) ? ' has-error' : '' ?>">
                    <label for="event_date">Event date</label>
                    <input type="date" id="event_date" name="event_date" required
                           min="<?= e($earliest->format('Y-m-d')) ?>" max="<?= e($latest->format('Y-m-d')) ?>"
                           value="<?= e($values['event_date']) ?>">
                    <p class="field-hint">Earliest: <?= e($earliest->format('l, j F')) ?>.</p>
                    <?= fieldError($errors, 'event_date') ?>
                </div>
                <fieldset class="field field-full<?= isset($errors['time_slot']) ? ' has-error' : '' ?>">
                    <legend>Arrival time</legend>
                    <div class="choice-row">
                        <?php foreach ($timeSlots as $i => $slot): ?>
                            <label class="choice">
                                <input type="radio" name="time_slot" value="<?= e($slot) ?>" required
                                       <?= $values['time_slot'] === $slot ? 'checked' : '' ?>>
                                <span><?= e($slot) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="field-hint">We arrive within this hour and set up before serving.</p>
                    <?= fieldError($errors, 'time_slot') ?>
                </fieldset>
                <div class="field field-full<?= isset($errors['notes']) ? ' has-error' : '' ?>">
                    <label for="notes">Notes <span class="muted">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="3" maxlength="500" data-counter
                              placeholder="Allergies, access instructions, event type"><?= e($values['notes']) ?></textarea>
                    <p class="field-hint"><span class="char-count">0</span>/500</p>
                    <?= fieldError($errors, 'notes') ?>
                </div>
            </div>

            <div class="field<?= isset($errors['terms']) ? ' has-error' : '' ?>">
                <label class="checkbox">
                    <input type="checkbox" name="terms" value="1" required <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
                    <span>I agree to the <a href="about.php#delivery" target="_blank">delivery and cancellation terms</a>.</span>
                </label>
                <?= fieldError($errors, 'terms') ?>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Continue to payment</button>
            <p class="field-hint center">Payment is handled by our secure payment partner on the next page.</p>
        </form>

        <aside class="summary">
            <h2>Order summary</h2>
            <ul class="summary-items">
                <?php foreach ($cart['lines'] as $line): ?>
                    <li>
                        <img src="<?= e($line['package']['image']) ?>" alt="" width="56" height="42">
                        <span class="summary-item-name"><?= e($line['package']['name']) ?> <span class="muted">x <?= (int) $line['guests'] ?> guests</span></span>
                        <span><?= money($line['line_total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="summary-lines">
                <div><dt>Subtotal</dt><dd><?= money($cart['subtotal']) ?></dd></div>
                <div><dt>Delivery and set-up</dt><dd><?= $cart['delivery'] > 0 ? money($cart['delivery']) : 'Free' ?></dd></div>
                <div class="summary-total"><dt>Total</dt><dd><?= money($cart['total']) ?></dd></div>
            </dl>
            <p class="field-hint"><a href="cart.php">Edit cart</a></p>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
