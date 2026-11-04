<?php
// Stand-in for a third-party payment gateway. The project brief says payment is
// handled externally, so this page only lets you choose the gateway's answer
// (success or declined) and then does what the site must do in each case.
require_once __DIR__ . '/includes/config.php';

$ref = strtoupper(getValue('ref'));
$order = validOrderRef($ref) ? getOrderByRef($ref) : false;

// Only the browser that placed the order may pay for it.
if (!$order || !ownsOrder($ref)) {
    flash('error', 'We could not find that order. Use your order number and email to track it.');
    redirect('track.php');
}

$payable = in_array($order['status'], ['pending_payment', 'payment_failed'], true);

if (isPost() && $payable) {
    checkCsrf();
    $outcome = postValue('outcome');

    if ($outcome === 'success') {
        setOrderStatus($order['id'], 'confirmed', 'Payment received');
        $order = getOrderByRef($ref);
        cartClear();
        $sent = sendOrderConfirmation($order);
        flash('success', 'Payment successful. Your order is confirmed'
            . ($sent ? ' and a confirmation was emailed to ' . $order['email'] . '.' : '. (The confirmation email could not be sent, but your order is saved.)'));
        redirect('track.php?ref=' . urlencode($ref));
    }

    if ($outcome === 'failed') {
        setOrderStatus($order['id'], 'payment_failed', 'Payment was declined');
        $sent = sendPaymentFailure($order);
        flash('error', 'Your payment was declined and you have not been charged. You can try again below.'
            . ($sent ? ' A payment failure email was sent to ' . $order['email'] . '.' : ' The payment failure email could not be sent (saved in the email log).'));
        redirect('payment.php?ref=' . urlencode($ref));
    }
}

if (!$payable) {
    redirect('track.php?ref=' . urlencode($ref));
}

$photo = getOrderImage($order['id']);

$pageTitle = 'Payment';
require __DIR__ . '/includes/header.php';
?>

<section class="section-tight">
    <div class="container pay-wrap">
        <div class="pay-card">
            <img class="pay-image" src="<?= e($photo['image']) ?>" alt="<?= e($photo['image_alt']) ?>">
            <p class="pay-badge">Secure payment partner (simulation)</p>
            <h1>Pay <?= money($order['total']) ?></h1>
            <dl class="summary-lines">
                <div><dt>Merchant</dt><dd><?= e(SITE_NAME) ?></dd></div>
                <div><dt>Order</dt><dd><?= e($order['order_ref']) ?></dd></div>
                <div><dt>Event date</dt><dd><?= e(date('D, j M Y', strtotime($order['event_date']))) ?></dd></div>
            </dl>

            <?php if ($order['status'] === 'payment_failed'): ?>
                <p class="alert alert-error">The last attempt was declined. Nothing was charged.</p>
            <?php endif; ?>

            <p class="pay-note">
                In the live site this step happens on the payment provider's page.
                For this project, choose what the provider reports back:
            </p>

            <form method="post" action="payment.php?ref=<?= e(urlencode($ref)) ?>" class="pay-actions">
                <?= csrfField() ?>
                <button class="btn btn-primary btn-block" type="submit" name="outcome" value="success">
                    Payment succeeds
                </button>
                <button class="btn btn-secondary btn-block" type="submit" name="outcome" value="failed">
                    Card is declined
                </button>
            </form>
            <p class="field-hint center"><a href="cart.php">Cancel and return to cart</a></p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
