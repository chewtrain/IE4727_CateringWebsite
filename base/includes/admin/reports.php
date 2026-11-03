<?php
// Staff view: sales reports using GROUP BY and aggregate functions.
// Only paid orders count towards sales.
defined('IN_ADMIN') or exit;

$paid = "o.status IN ('confirmed', 'preparing', 'out_for_delivery', 'delivered')";

// Optional event-date range from the query string.
$from = getValue('from');
$to = getValue('to');
$validDate = function ($value) {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
};
$rangeSql = '';
$rangeParams = [];
if ($from !== '' && $validDate($from)) {
    $rangeSql .= ' AND o.event_date >= ?';
    $rangeParams[] = $from;
} else {
    $from = '';
}
if ($to !== '' && $validDate($to)) {
    $rangeSql .= ' AND o.event_date <= ?';
    $rangeParams[] = $to;
} else {
    $to = '';
}

function runReport($sql, $params)
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$kpi = runReport(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(o.total), 0) AS revenue, COALESCE(AVG(o.total), 0) AS avg_order,
            COALESCE(SUM((SELECT SUM(i.guests) FROM order_items i WHERE i.order_id = o.id)), 0) AS guests
     FROM orders o WHERE $paid $rangeSql",
    $rangeParams
)[0];

$byPackage = runReport(
    "SELECT i.package_name, COUNT(DISTINCT o.id) AS orders, SUM(i.guests) AS guests, SUM(i.line_total) AS revenue
     FROM order_items i JOIN orders o ON o.id = i.order_id
     WHERE $paid $rangeSql
     GROUP BY i.package_name
     ORDER BY revenue DESC",
    $rangeParams
);

$byMonth = runReport(
    "SELECT DATE_FORMAT(o.event_date, '%Y-%m') AS month, COUNT(*) AS orders, SUM(o.total) AS revenue
     FROM orders o
     WHERE $paid $rangeSql
     GROUP BY month
     ORDER BY month",
    $rangeParams
);

$topDishes = runReport(
    "SELECT d.dish_name, d.category_name, COUNT(*) AS times, SUM(i.guests) AS guests
     FROM order_item_dishes d
     JOIN order_items i ON i.id = d.order_item_id
     JOIN orders o ON o.id = i.order_id
     WHERE $paid $rangeSql
     GROUP BY d.dish_name, d.category_name
     ORDER BY times DESC, guests DESC
     LIMIT 8",
    $rangeParams
);

$bySlot = runReport(
    "SELECT o.time_slot, COUNT(*) AS orders
     FROM orders o WHERE $paid $rangeSql
     GROUP BY o.time_slot ORDER BY o.time_slot",
    $rangeParams
);

$ratings = db()->query('SELECT COUNT(*) AS reviews, AVG(rating) AS average FROM feedback')->fetch();

// Largest value in a column, used to scale the bars.
function maxOf($rows, $column)
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (float) $row[$column]);
    }
    return $max > 0 ? $max : 1;
}

$pageTitle = 'Reports | Staff';
$adminHeading = 'Reports';
$adminIntro = 'Sales, popular dishes and busy time slots, worked out from paid orders.';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">

        <form class="filter-bar" method="get" action="admin.php">
            <input type="hidden" name="view" value="reports">
            <div class="range">
                <label for="from">Event date from</label>
                <input type="date" id="from" name="from" value="<?= e($from) ?>">
                <label for="to">to</label>
                <input type="date" id="to" name="to" value="<?= e($to) ?>">
                <button class="btn btn-small btn-secondary" type="submit">Apply</button>
                <?php if ($from !== '' || $to !== ''): ?><a href="admin.php?view=reports">Clear</a><?php endif; ?>
            </div>
            <p class="muted">Paid orders only<?= $from !== '' || $to !== '' ? ', filtered by event date' : ', all dates' ?>.</p>
        </form>

        <ul class="kpi-grid">
            <li class="kpi"><span class="kpi-label">Revenue</span><strong class="kpi-value"><?= money($kpi['revenue']) ?></strong></li>
            <li class="kpi"><span class="kpi-label">Paid orders</span><strong class="kpi-value"><?= (int) $kpi['orders'] ?></strong></li>
            <li class="kpi"><span class="kpi-label">Average order</span><strong class="kpi-value"><?= money($kpi['avg_order']) ?></strong></li>
            <li class="kpi"><span class="kpi-label">Guests served</span><strong class="kpi-value"><?= number_format((int) $kpi['guests']) ?></strong></li>
            <li class="kpi"><span class="kpi-label">Customer rating</span><strong class="kpi-value"><?= $ratings['reviews'] ? number_format((float) $ratings['average'], 1) . ' / 5' : '-' ?></strong><span class="kpi-sub"><?= (int) $ratings['reviews'] ?> reviews</span></li>
        </ul>

        <div class="report-grid">
            <section class="report-card">
                <h2>Revenue by package</h2>
                <?php $max = maxOf($byPackage, 'revenue'); ?>
                <table class="bar-table">
                    <thead><tr><th scope="col">Package</th><th scope="col">Revenue</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Guests</th></tr></thead>
                    <tbody>
                        <?php foreach ($byPackage as $row): ?>
                            <tr>
                                <th scope="row"><?= e($row['package_name']) ?></th>
                                <td class="bar-cell" title="<?= e($row['package_name'] . ': ' . money($row['revenue'])) ?>">
                                    <span class="bar-wrap"><span class="bar-track"><span class="bar" style="width: <?= round($row['revenue'] / $max * 100, 1) ?>%"></span></span><span class="bar-value"><?= money($row['revenue']) ?></span></span>
                                </td>
                                <td class="num"><?= (int) $row['orders'] ?></td>
                                <td class="num"><?= (int) $row['guests'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$byPackage): ?><tr><td colspan="4" class="empty">No paid orders in this range.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="report-card">
                <h2>Revenue by event month</h2>
                <?php $max = maxOf($byMonth, 'revenue'); ?>
                <table class="bar-table">
                    <thead><tr><th scope="col">Month</th><th scope="col">Revenue</th><th scope="col" class="num">Orders</th></tr></thead>
                    <tbody>
                        <?php foreach ($byMonth as $row): ?>
                            <?php $label = date('M Y', strtotime($row['month'] . '-01')); ?>
                            <tr>
                                <th scope="row"><?= e($label) ?></th>
                                <td class="bar-cell" title="<?= e($label . ': ' . money($row['revenue'])) ?>">
                                    <span class="bar-wrap"><span class="bar-track"><span class="bar" style="width: <?= round($row['revenue'] / $max * 100, 1) ?>%"></span></span><span class="bar-value"><?= money($row['revenue']) ?></span></span>
                                </td>
                                <td class="num"><?= (int) $row['orders'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$byMonth): ?><tr><td colspan="3" class="empty">No paid orders in this range.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="report-card">
                <h2>Most chosen dishes</h2>
                <?php $max = maxOf($topDishes, 'times'); ?>
                <table class="bar-table">
                    <thead><tr><th scope="col">Dish</th><th scope="col">Times chosen</th><th scope="col" class="num">Guests</th></tr></thead>
                    <tbody>
                        <?php foreach ($topDishes as $row): ?>
                            <tr>
                                <th scope="row"><?= e($row['dish_name']) ?><span class="muted"> &middot; <?= e($row['category_name']) ?></span></th>
                                <td class="bar-cell" title="<?= e($row['dish_name'] . ': chosen ' . $row['times'] . ' times') ?>">
                                    <span class="bar-wrap"><span class="bar-track"><span class="bar" style="width: <?= round($row['times'] / $max * 100, 1) ?>%"></span></span><span class="bar-value"><?= (int) $row['times'] ?></span></span>
                                </td>
                                <td class="num"><?= number_format((int) $row['guests']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$topDishes): ?><tr><td colspan="3" class="empty">No paid orders in this range.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="report-card">
                <h2>Orders by arrival time</h2>
                <?php $max = maxOf($bySlot, 'orders'); ?>
                <table class="bar-table">
                    <thead><tr><th scope="col">Time slot</th><th scope="col">Orders</th></tr></thead>
                    <tbody>
                        <?php foreach ($bySlot as $row): ?>
                            <tr>
                                <th scope="row"><?= e($row['time_slot']) ?></th>
                                <td class="bar-cell" title="<?= e($row['time_slot'] . ': ' . $row['orders'] . ' orders') ?>">
                                    <span class="bar-wrap"><span class="bar-track"><span class="bar" style="width: <?= round($row['orders'] / $max * 100, 1) ?>%"></span></span><span class="bar-value"><?= (int) $row['orders'] ?></span></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$bySlot): ?><tr><td colspan="2" class="empty">No paid orders in this range.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
