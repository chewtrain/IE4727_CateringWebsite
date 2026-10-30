<?php
// Staff view: all orders, filtered by status and searchable.
defined('IN_ADMIN') or exit;

$filter = getValue('status');
$search = getValue('q');

$where = [];
$params = [];
if ($filter !== '' && isset($statusLabels[$filter])) {
    $where[] = 'o.status = ?';
    $params[] = $filter;
} else {
    $filter = '';
}
if ($search !== '') {
    $where[] = '(o.order_ref LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$stmt = db()->prepare(
    'SELECT o.id, o.order_ref, o.customer_name, o.email, o.event_date, o.time_slot, o.total, o.status,
            SUM(i.guests) AS guests
     FROM orders o JOIN order_items i ON i.order_id = o.id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' GROUP BY o.id ORDER BY o.event_date DESC, o.id DESC'
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Count per status for the filter chips.
$counts = [];
foreach (db()->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

// Orders that need attention: paid and due within the next 3 days.
$upcoming = db()->query(
    "SELECT COUNT(*) FROM orders
     WHERE status IN ('confirmed', 'preparing') AND event_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY"
)->fetchColumn();

$pageTitle = 'Orders | Staff';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">
        <div class="admin-head">
            <h1>Orders</h1>
            <?php if ($upcoming > 0): ?>
                <p class="alert alert-info"><?= (int) $upcoming ?> paid order(s) are due in the next 3 days.</p>
            <?php endif; ?>
        </div>

        <form class="filter-bar" method="get" action="admin.php">
            <input type="hidden" name="view" value="orders">
            <div class="chip-row">
                <a class="chip<?= $filter === '' ? ' active' : '' ?>" href="admin.php?view=orders">All <span><?= array_sum($counts) ?></span></a>
                <?php foreach ($statusLabels as $key => $label): ?>
                    <?php if (!empty($counts[$key])): ?>
                        <a class="chip<?= $filter === $key ? ' active' : '' ?>"
                           href="admin.php?view=orders&amp;status=<?= e($key) ?>"><?= e($label) ?> <span><?= $counts[$key] ?></span></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="search">
                <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
                <label class="sr-only" for="q">Search orders</label>
                <input type="search" id="q" name="q" placeholder="Order number, name or email" value="<?= e($search) ?>">
                <button class="btn btn-small btn-secondary" type="submit">Search</button>
            </div>
        </form>

        <div class="table-wrap">
            <table class="table admin-table">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Event</th>
                        <th scope="col" class="num">Guests</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="6" class="empty">No orders match.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><a href="admin.php?view=order&amp;id=<?= (int) $order['id'] ?>"><strong><?= e($order['order_ref']) ?></strong></a></td>
                            <td><?= e($order['customer_name']) ?><br><span class="muted"><?= e($order['email']) ?></span></td>
                            <td><?= e(date('D, j M Y', strtotime($order['event_date']))) ?><br><span class="muted"><?= e($order['time_slot']) ?></span></td>
                            <td class="num"><?= (int) $order['guests'] ?></td>
                            <td class="num"><?= money($order['total']) ?></td>
                            <td><span class="badge badge-<?= e($order['status']) ?>"><?= e(statusLabel($order['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
