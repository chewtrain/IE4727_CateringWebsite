<?php
require_once __DIR__ . '/includes/config.php';

// Live numbers from the database for the "in numbers" strip.
$stats = db()->query(
    "SELECT COUNT(*) AS orders_delivered, COALESCE(SUM(i.guests), 0) AS guests_served
     FROM orders o JOIN order_items i ON i.order_id = o.id
     WHERE o.status = 'delivered'"
)->fetch();
$dishCount = db()->query('SELECT COUNT(*) FROM dishes WHERE is_active = 1')->fetchColumn();

$pageTitle = 'About Us';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div class="container">
        <h1>Food for the people<br>you care about.</h1>
        <p class="lead"><?= e(SITE_NAME) ?> is a small Singapore kitchen cooking local buffets for offices, families and celebrations.</p>
    </div>
</section>

<section class="section-tight">
    <div class="container feature">
        <img class="feature-image reveal" src="images/occasion-family.jpg" alt="A family-style table of Asian dishes seen from above" loading="lazy">
        <div class="feature-text reveal">
            <h2>Cooked like it is for our own family.</h2>
            <p class="lead">We started with one wok and a simple idea: catering should taste like home cooking, not like it sat in a van for two hours.</p>
            <p class="body-text">Every buffet is cooked on the morning of your event, using recipes from our Malay, Chinese, Peranakan and Indian kitchens. We keep menus short so every dish is one we are proud of.</p>
        </div>
    </div>
</section>

<section class="section-tight section-alt">
    <div class="container">
        <ul class="stat-row reveal">
            <li><strong><?= number_format((int) $stats['orders_delivered']) ?></strong><span>buffets delivered</span></li>
            <li><strong><?= number_format((int) $stats['guests_served']) ?></strong><span>guests served</span></li>
            <li><strong><?= (int) $dishCount ?></strong><span>dishes on our menu</span></li>
            <li><strong><?= LEAD_DAYS ?> days</strong><span>minimum notice</span></li>
        </ul>
    </div>
</section>

<section class="section" id="delivery">
    <div class="container">
        <div class="section-head reveal">
            <h2>Delivery and set-up.</h2>
            <p class="lead">Everything you need to know before you order.</p>
        </div>

        <div class="info-grid">
            <div class="info-card reveal">
                <h3>Delivery</h3>
                <p>Islandwide delivery, <?= money(DELIVERY_FEE) ?> per order. Free for orders of $<?= number_format(FREE_DELIVERY_MIN) ?> and above.</p>
            </div>
            <div class="info-card reveal">
                <h3>Notice</h3>
                <p>Order at least <?= LEAD_DAYS ?> days ahead so we can buy fresh ingredients. We take bookings up to 6 months ahead.</p>
            </div>
            <div class="info-card reveal">
                <h3>Set-up</h3>
                <p>We arrive within your chosen hour, set up chafing dishes, warmers and serving ware, and collect everything after 4 hours.</p>
            </div>
            <div class="info-card reveal">
                <h3>Changes and cancellations</h3>
                <p>Change guest numbers or dishes up to 48 hours before delivery. Cancel more than 48 hours ahead for a full refund.</p>
            </div>
        </div>

        <div class="table-wrap reveal">
            <table class="table">
                <caption>Delivery time slots</caption>
                <thead>
                    <tr><th scope="col">Arrival window</th><th scope="col">Best for</th><th scope="col">Food served from</th></tr>
                </thead>
                <tbody>
                    <tr><td>11:00 - 12:00</td><td>Office lunches, seminars</td><td>12:00</td></tr>
                    <tr><td>12:00 - 13:00</td><td>Weekend lunches, open houses</td><td>13:00</td></tr>
                    <tr><td>15:00 - 16:00</td><td>High tea, afternoon events</td><td>16:00</td></tr>
                    <tr><td>18:00 - 19:00</td><td>Dinners, birthdays, weddings</td><td>19:00</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container narrow">
        <div class="section-head reveal">
            <h2>Questions.</h2>
        </div>
        <div class="faq reveal">
            <details>
                <summary>Do I need an account to order?</summary>
                <p>No. Check out as a guest. Your order number and email are all you need to track it.</p>
            </details>
            <details>
                <summary>Can I mix packages for one event?</summary>
                <p>Yes. Add more than one package to your cart, for example a Classic Local lunch and a Dim Sum High Tea in the afternoon.</p>
            </details>
            <details>
                <summary>Do you cater for vegetarians?</summary>
                <p>Every package has vegetarian dishes, marked on the menu. Tell us about allergies in the notes at checkout.</p>
            </details>
            <details>
                <summary>How do I know my order is on its way?</summary>
                <p>We email you at every step: confirmed, preparing, out for delivery and delivered. You can also check the <a href="track.php">tracking page</a> at any time.</p>
            </details>
            <details>
                <summary>What if my payment fails?</summary>
                <p>You are not charged, and your order is saved. Open it from the tracking page and try again.</p>
            </details>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
