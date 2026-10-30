<?php
require_once __DIR__ . '/includes/config.php';

$lookupRef = '';
$lookupEmail = '';
$error = '';

// Look up by order number + email (from the form, or the link in our emails).
$source = isPost() ? $_POST : $_GET;
if (isset($source['email'])) {
    if (isPost()) {
        checkCsrf();
    }
    $lookupRef = strtoupper(isset($source['ref']) && is_string($source['ref']) ? trim($source['ref']) : '');
    $lookupEmail = strtolower(is_string($source['email']) ? trim($source['email']) : '');

    $found = validOrderRef($lookupRef) && validEmail($lookupEmail) ? getOrderByRef($lookupRef) : false;
    if ($found && hash_equals(strtolower($found['email']), $lookupEmail)) {
        rememberOrder($lookupRef);
        redirect('track.php?ref=' . urlencode($lookupRef));
    }
    $error = 'We could not find an order with that number and email. Check both and try again.';
}

// Show an order this browser placed or has already looked up.
$order = false;
$ref = strtoupper(getValue('ref'));
if ($ref !== '' && !isset($source['email'])) {
    if (ownsOrder($ref)) {
        $order = getOrderByRef($ref);
    } else {
        $lookupRef = $ref;   // pre-fill the form; the email proves it is theirs
    }
}

if ($order) {
    $items = getOrderItems($order['id']);
    $history = getOrderHistory($order['id']);

    // When each step of the timeline was reached.
    $reached = [];
    foreach ($history as $event) {
        $reached[$event['status']] = $event['changed_at'];
    }
    $steps = [
        'pending_payment'  => 'Order placed',
        'confirmed'        => 'Confirmed',
        'preparing'        => 'Preparing',
        'out_for_delivery' => 'Out for delivery',
        'delivered'        => 'Delivered',
    ];
    $stepKeys = array_keys($steps);
    $currentIndex = array_search($order['status'], $stepKeys, true);
}

$pageTitle = $order ? 'Order ' . $order['order_ref'] : 'Track Your Order';
$pageScripts = ['js/forms.js'];
require __DIR__ . '/includes/header.php';
?>

<?php if (!$order): ?>
<section class="page-head">
    <div class="container">
        <h1>Track your order.</h1>
        <p class="lead">Enter the order number from your confirmation email.</p>
    </div>
</section>

<section class="section-tight">
    <div class="container narrow">
        <form class="form-card" method="post" action="track.php" novalidate data-validate>
            <?= csrfField() ?>
            <?php if ($error): ?>
                <p class="alert alert-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
            <div class="field">
                <label for="ref">Order number</label>
                <input type="text" id="ref" name="ref" required maxlength="9" placeholder="MC-7K3QX2"
                       pattern="[Mm][Cc]-[A-Za-z0-9]{6}" data-pattern-message="Order numbers look like MC-7K3QX2."
                       autocapitalize="characters" value="<?= e($lookupRef) ?>">
                <p class="field-error" id="ref-error" hidden></p>
            </div>
            <div class="field">
                <label for="email">Email used for the order</label>
                <input type="email" id="email" name="email" required maxlength="120" autocomplete="email"
                       value="<?= e($lookupEmail) ?>">
                <p class="field-error" id="email-error" hidden></p>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Find my order</button>
        </form>
    </div>
</section>

<?php else: ?>
<section class="page-head">
    <div class="container">
        <p class="eyebrow">Order <?= e($order['order_ref']) ?></p>
        <h1><?= e(statusLabel($order['status'])) ?>.</h1>
        <p class="lead">
            <?php if ($order['status'] === 'pending_payment' || $order['status'] === 'payment_failed'): ?>
                This order is not paid yet, so it is not confirmed.
            <?php elseif ($order['status'] === 'cancelled'): ?>
                This order was cancelled. Contact us if you have questions.
            <?php elseif ($order['status'] === 'delivered'): ?>
                Delivered on <?= e(date('l, j F', strtotime($order['event_date']))) ?>. We hope your guests enjoyed it.
            <?php else: ?>
                Arriving <?= e(date('l, j F', strtotime($order['event_date']))) ?>, <?= e($order['time_slot']) ?>.
            <?php endif; ?>
        </p>
        <?php if ($order['status'] === 'pending_payment' || $order['status'] === 'payment_failed'): ?>
            <p class="page-head-actions"><a class="btn btn-primary" href="payment.php?ref=<?= e(urlencode($order['order_ref'])) ?>">Complete payment</a></p>
        <?php endif; ?>
    </div>
</section>

<section class="section-tight">
    <div class="container">
        <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'payment_failed'): ?>
            <ol class="timeline" aria-label="Order progress">
                <?php foreach ($steps as $key => $label): ?>
                    <?php
                    $index = array_search($key, $stepKeys, true);
                    $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'todo');
                    ?>
                    <li class="timeline-step is-<?= $state ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
                        <span class="timeline-dot"></span>
                        <span class="timeline-label"><?= e($label) ?></span>
                        <span class="timeline-time">
                            <?= isset($reached[$key]) ? e(date('j M, g:i a', strtotime($reached[$key]))) : '&nbsp;' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="order-grid">
            <div>
                <h2 class="block-title">What you ordered</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th scope="col">Package</th><th scope="col">Guests</th><th scope="col" class="num">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['package_name']) ?></strong>
                                        <ul class="dish-list">
                                            <?php foreach ($item['dishes'] as $dish): ?>
                                                <li><?= e($dish['dish_name']) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td><?= (int) $item['guests'] ?></td>
                                    <td class="num"><?= money($item['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="2">Delivery and set-up</td><td class="num"><?= $order['delivery_fee'] > 0 ? money($order['delivery_fee']) : 'Free' ?></td></tr>
                            <tr class="summary-total"><td colspan="2">Total</td><td class="num"><?= money($order['total']) ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <aside class="summary">
                <h2>Delivery</h2>
                <dl class="detail-list">
                    <dt>Date</dt><dd><?= e(date('l, j F Y', strtotime($order['event_date']))) ?></dd>
                    <dt>Arrival</dt><dd><?= e($order['time_slot']) ?></dd>
                    <dt>Address</dt><dd><?= e($order['address']) ?>, Singapore <?= e($order['postal_code']) ?></dd>
                    <dt>Contact</dt><dd><?= e($order['customer_name']) ?><br><?= e($order['phone']) ?></dd>
                    <?php if ($order['notes'] !== ''): ?>
                        <dt>Notes</dt><dd><?= e($order['notes']) ?></dd>
                    <?php endif; ?>
                </dl>
                <p class="field-hint">Need to change something? Call <?= e(CONTACT_PHONE) ?> at least 48 hours before delivery.</p>
                <?php if ($order['status'] === 'delivered'): ?>
                    <a class="btn btn-secondary btn-block" href="contact.php?ref=<?= e(urlencode($order['order_ref'])) ?>">Leave feedback</a>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
