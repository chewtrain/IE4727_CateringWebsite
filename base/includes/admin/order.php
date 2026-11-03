<?php
// Staff view: one order, with a form to move it to its next status.
defined('IN_ADMIN') or exit;

// Which statuses an order can move to from each status.
$nextStatuses = [
    'pending_payment'  => ['cancelled'],
    'payment_failed'   => ['cancelled'],
    'confirmed'        => ['preparing', 'cancelled'],
    'preparing'        => ['out_for_delivery', 'cancelled'],
    'out_for_delivery' => ['delivered'],
    'delivered'        => [],
    'cancelled'        => [],
];

$orderId = (int) getValue('id');
$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'That order does not exist.');
    redirect('admin.php?view=orders');
}

$allowed = $nextStatuses[$order['status']];
$formError = '';

// Only orders that were never paid, or were cancelled, can be deleted.
// Paid orders stay for the sales records.
$deletable = in_array($order['status'], ['pending_payment', 'payment_failed', 'cancelled'], true);

if (isPost() && postValue('action') === 'delete_order') {
    checkCsrf();
    if ($deletable) {
        // Items, chosen dishes and history go too (ON DELETE CASCADE).
        db()->prepare('DELETE FROM orders WHERE id = ?')->execute([$order['id']]);
        flash('success', 'Order ' . $order['order_ref'] . ' was deleted.');
        redirect('admin.php?view=orders');
    }
    flash('error', 'Paid orders cannot be deleted. Cancel the order instead.');
    redirect('admin.php?view=order&id=' . $order['id']);
}

if (isPost() && postValue('action') === 'update_status') {
    checkCsrf();
    $newStatus = postValue('status');
    $note = postValue('note');

    if (!in_array($newStatus, $allowed, true)) {
        $formError = 'Choose one of the listed statuses.';
    } elseif (strlen($note) > 200) {
        $formError = 'Keep the note under 200 characters.';
    } else {
        setOrderStatus($order['id'], $newStatus, $note);
        $message = 'Order ' . $order['order_ref'] . ' is now ' . statusLabel($newStatus) . '.';
        if (!empty($_POST['notify'])) {
            $sent = sendStatusUpdate($order, $newStatus, $note);
            $message .= $sent ? ' The customer was emailed.' : ' The email could not be sent (saved in the email log).';
        }
        flash('success', $message);
        redirect('admin.php?view=order&id=' . $order['id']);
    }
}

$items = getOrderItems($order['id']);
$history = getOrderHistory($order['id']);

$pageTitle = 'Order ' . $order['order_ref'] . ' | Staff';
$adminHeading = 'Order ' . $order['order_ref'];
$adminCrumb = $order['order_ref'];
$adminIntro = $order['customer_name'] . ', ' . date('l, j F Y', strtotime($order['event_date'])) . ', ' . $order['time_slot'];
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">
        <p class="status-line">Status: <span class="badge badge-<?= e($order['status']) ?>"><?= e(statusLabel($order['status'])) ?></span></p>

        <div class="order-grid">
            <div>
                <h2 class="block-title">Items</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th scope="col">Package</th><th scope="col">Dishes</th><th scope="col" class="num">Guests</th><th scope="col" class="num">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?= e($item['package_name']) ?></strong><br><span class="muted"><?= money($item['price_per_pax']) ?> per guest</span></td>
                                    <td>
                                        <ul class="dish-list">
                                            <?php foreach ($item['dishes'] as $dish): ?>
                                                <li><?= e($dish['dish_name']) ?> <span class="muted">(<?= e($dish['category_name']) ?>)</span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td class="num"><?= (int) $item['guests'] ?></td>
                                    <td class="num"><?= money($item['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3">Delivery</td><td class="num"><?= money($order['delivery_fee']) ?></td></tr>
                            <tr class="summary-total"><td colspan="3">Total</td><td class="num"><?= money($order['total']) ?></td></tr>
                        </tfoot>
                    </table>
                </div>

                <h2 class="block-title">History</h2>
                <ol class="history">
                    <?php foreach ($history as $event): ?>
                        <li>
                            <span class="badge badge-<?= e($event['status']) ?>"><?= e(statusLabel($event['status'])) ?></span>
                            <span class="muted"><?= e(date('j M Y, g:i a', strtotime($event['changed_at']))) ?></span>
                            <?php if ($event['note'] !== ''): ?><span><?= e($event['note']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <aside>
                <div class="summary">
                    <h2>Update status</h2>
                    <?php if (!$allowed): ?>
                        <p class="muted">This order is <?= e(strtolower(statusLabel($order['status']))) ?>. No further changes.</p>
                    <?php else: ?>
                        <form method="post" action="admin.php?view=order&amp;id=<?= (int) $order['id'] ?>" class="status-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <?php if ($formError): ?><p class="alert alert-error"><?= e($formError) ?></p><?php endif; ?>
                            <fieldset class="field">
                                <legend>New status</legend>
                                <?php foreach ($allowed as $i => $status): ?>
                                    <label class="choice choice-block">
                                        <input type="radio" name="status" value="<?= e($status) ?>" required <?= $i === 0 ? 'checked' : '' ?>>
                                        <span><?= e(statusLabel($status)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <div class="field">
                                <label for="note">Note to customer <span class="muted">(optional)</span></label>
                                <input type="text" id="note" name="note" maxlength="200" placeholder="e.g. Driver: Ahmad, SJM 1234 A">
                            </div>
                            <label class="checkbox">
                                <input type="checkbox" name="notify" value="1" checked>
                                <span>Email the customer</span>
                            </label>
                            <button class="btn btn-primary btn-block" type="submit">Update order</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="summary">
                    <h2>Customer</h2>
                    <dl class="detail-list">
                        <dt>Name</dt><dd><?= e($order['customer_name']) ?></dd>
                        <dt>Email</dt><dd><?= e($order['email']) ?></dd>
                        <dt>Phone</dt><dd><?= e($order['phone']) ?></dd>
                        <dt>Delivery</dt><dd><?= e(date('l, j F Y', strtotime($order['event_date']))) ?><br><?= e($order['time_slot']) ?></dd>
                        <dt>Address</dt><dd><?= e($order['address']) ?>, Singapore <?= e($order['postal_code']) ?></dd>
                        <?php if ($order['notes'] !== ''): ?><dt>Notes</dt><dd><?= e($order['notes']) ?></dd><?php endif; ?>
                        <dt>Placed</dt><dd><?= e(date('j M Y, g:i a', strtotime($order['created_at']))) ?></dd>
                    </dl>
                </div>

                <?php if ($deletable): ?>
                    <form class="summary danger-zone" method="post" action="admin.php?view=order&amp;id=<?= (int) $order['id'] ?>"
                          data-confirm="Delete order <?= e($order['order_ref']) ?> and its history? This cannot be undone.">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_order">
                        <h2>Delete order</h2>
                        <p class="field-hint">Unpaid and cancelled orders can be removed. Paid orders are kept for the sales records.</p>
                        <button class="btn btn-danger btn-block" type="submit">Delete order</button>
                    </form>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
