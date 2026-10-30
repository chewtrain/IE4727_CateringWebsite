<?php
require_once __DIR__ . '/includes/config.php';

// Sort order comes from the query string; only known values are accepted.
$sortOptions = [
    'recommended' => ['label' => 'Recommended',        'sql' => 'p.sort_order'],
    'price_low'   => ['label' => 'Price: low to high', 'sql' => 'p.price_per_pax ASC'],
    'price_high'  => ['label' => 'Price: high to low', 'sql' => 'p.price_per_pax DESC'],
    'guests'      => ['label' => 'Smallest minimum',   'sql' => 'p.min_pax ASC, p.price_per_pax ASC'],
];
$sort = getValue('sort');
if (!isset($sortOptions[$sort])) {
    $sort = 'recommended';
}

// Each package with how many dishes it offers and how many the customer picks.
$packages = db()->query(
    'SELECT p.*,
            (SELECT SUM(r.choose_count) FROM package_rules r WHERE r.package_id = p.id) AS dishes_to_pick,
            COUNT(d.id) AS dishes_offered,
            SUM(d.is_vegetarian) AS vegetarian_dishes
     FROM packages p
     LEFT JOIN package_dishes pd ON pd.package_id = p.id
     LEFT JOIN dishes d ON d.id = pd.dish_id AND d.is_active = 1
     WHERE p.is_active = 1
     GROUP BY p.id
     ORDER BY ' . $sortOptions[$sort]['sql']
)->fetchAll();

// Category rules for the comparison table, e.g. "3 Mains".
$rules = [];
$ruleRows = db()->query(
    'SELECT r.package_id, r.choose_count, c.name
     FROM package_rules r JOIN categories c ON c.id = r.category_id
     ORDER BY c.sort_order'
);
foreach ($ruleRows as $row) {
    $rules[$row['package_id']][] = $row['choose_count'] . ' ' . $row['name'];
}

$pageTitle = 'Buffet Packages';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div class="container">
        <h1>Buffet packages.</h1>
        <p class="lead">Priced per guest. Delivery, warmers and full set-up included with every package.</p>
    </div>
</section>

<section class="section-tight">
    <div class="container">
        <form class="sort-bar" method="get" action="buffets.php">
            <p class="sort-count"><?= count($packages) ?> packages</p>
            <label for="sort">Sort by</label>
            <select id="sort" name="sort" data-autosubmit>
                <?php foreach ($sortOptions as $value => $option): ?>
                    <option value="<?= e($value) ?>"<?= $value === $sort ? ' selected' : '' ?>><?= e($option['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-small btn-secondary" type="submit">Sort</button></noscript>
        </form>

        <div class="package-list">
            <?php foreach ($packages as $pkg): ?>
                <article class="package-row reveal">
                    <img src="<?= e($pkg['image']) ?>" alt="<?= e($pkg['image_alt']) ?>" loading="lazy">
                    <div class="package-row-body">
                        <h2><?= e($pkg['name']) ?></h2>
                        <p class="card-tagline"><?= e($pkg['tagline']) ?></p>
                        <p class="card-text"><?= e($pkg['description']) ?></p>
                        <ul class="package-facts">
                            <li><strong><?= money($pkg['price_per_pax']) ?></strong> per guest</li>
                            <li>Min. <?= (int) $pkg['min_pax'] ?> guests</li>
                            <li><?= (int) $pkg['dishes_to_pick'] ?> dishes from a menu of <?= (int) $pkg['dishes_offered'] ?></li>
                            <li><?= (int) $pkg['vegetarian_dishes'] ?> vegetarian options</li>
                        </ul>
                        <a class="btn btn-primary" href="package.php?id=<?= (int) $pkg['id'] ?>">Choose dishes</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head reveal">
            <h2>Compare packages.</h2>
            <p class="lead">Prices include GST. Delivery is <?= money(DELIVERY_FEE) ?>, or free for orders of $<?= number_format(FREE_DELIVERY_MIN) ?> and above.</p>
        </div>
        <div class="table-wrap reveal">
            <table class="table">
                <caption class="sr-only">Comparison of buffet packages</caption>
                <thead>
                    <tr>
                        <th scope="col">Package</th>
                        <th scope="col">Per guest</th>
                        <th scope="col">Min. guests</th>
                        <th scope="col">You choose</th>
                        <th scope="col">From</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $pkg): ?>
                        <?php $minCost = $pkg['price_per_pax'] * $pkg['min_pax']; ?>
                        <tr>
                            <th scope="row"><a href="package.php?id=<?= (int) $pkg['id'] ?>"><?= e($pkg['name']) ?></a></th>
                            <td><?= money($pkg['price_per_pax']) ?></td>
                            <td><?= (int) $pkg['min_pax'] ?></td>
                            <td><?= e(implode(', ', isset($rules[$pkg['id']]) ? $rules[$pkg['id']] : [])) ?></td>
                            <td><?= money($minCost + deliveryFee($minCost)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-note">"From" is the price for the minimum number of guests, including delivery.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
