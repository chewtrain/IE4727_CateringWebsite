<?php
$pageTitle = 'Buffet Catering in Singapore';
require __DIR__ . '/includes/header.php';

// Featured buffet packages, straight from the database.
$packages = db()->query(
    'SELECT id, name, tagline, description, price_per_pax, min_pax, image, image_alt
     FROM packages WHERE is_featured = 1 AND is_active = 1 ORDER BY sort_order LIMIT 3'
)->fetchAll();

$occasions = [
    ['title' => 'Corporate events',  'text' => 'Meetings, seminars and launches.',   'image' => 'images/occasion-corporate.jpg',   'alt' => 'A buffet table with chafing dishes at an outdoor event'],
    ['title' => 'Celebrations',      'text' => 'Birthdays, weddings and festivals.', 'image' => 'images/occasion-celebration.jpg', 'alt' => 'Red and pink kueh apam on a cooling rack'],
    ['title' => 'Family gatherings', 'text' => 'Home parties of 20 guests or more.', 'image' => 'images/occasion-family.jpg',      'alt' => 'A family-style table of Asian dishes seen from above'],
];

$earliest = earliestDeliveryDate();
?>

<!-- Hero -->
<section class="hero">
    <div class="container hero-text reveal">
        <p class="eyebrow">Buffet catering for 20 to 500 guests</p>
        <h1>Feast together.<br>We'll handle the rest.</h1>
        <p class="lead">Local favourites cooked fresh on the day, delivered hot and set up on time, anywhere in Singapore.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="buffets.php">Browse buffets</a>
            <a class="link-arrow" href="#how-it-works">How it works</a>
        </div>
    </div>
    <div class="container-wide reveal">
        <img class="hero-image" src="images/hero-spread.jpg"
             alt="A table of Asian dishes seen from above: noodles, curries, spring rolls and prawns"
             width="1800" height="1200">
    </div>
</section>

<!-- Packages -->
<section class="section section-alt" id="packages">
    <div class="container">
        <div class="section-head reveal">
            <h2>Pick your buffet.</h2>
            <p class="lead">Every package is priced per guest and includes delivery, warmers and full set-up.</p>
        </div>

        <div class="card-grid">
            <?php foreach ($packages as $pkg): ?>
                <article class="card reveal">
                    <img class="card-image" src="<?= e($pkg['image']) ?>" alt="<?= e($pkg['image_alt']) ?>" loading="lazy">
                    <div class="card-body">
                        <h3><?= e($pkg['name']) ?></h3>
                        <p class="card-tagline"><?= e($pkg['tagline']) ?></p>
                        <p class="card-text"><?= e($pkg['description']) ?></p>
                        <p class="card-price">
                            <strong><?= money($pkg['price_per_pax']) ?></strong> per guest
                            <span class="card-min">Min. <?= (int) $pkg['min_pax'] ?> guests</span>
                        </p>
                        <a class="btn btn-primary btn-small" href="package.php?id=<?= (int) $pkg['id'] ?>">Choose dishes</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-head reveal">
            <h2>Ordering takes minutes.</h2>
            <p class="lead">No account needed. Order at least <?= LEAD_DAYS ?> days ahead.</p>
        </div>

        <ol class="steps">
            <li class="step reveal">
                <span class="step-number">1</span>
                <h3>Choose a package</h3>
                <p>Pick the buffet that suits your event and tell us how many guests.</p>
            </li>
            <li class="step reveal">
                <span class="step-number">2</span>
                <h3>Pick your dishes</h3>
                <p>Select your mains, sides and desserts from the package menu.</p>
            </li>
            <li class="step reveal">
                <span class="step-number">3</span>
                <h3>Set the date</h3>
                <p>Choose a delivery date and time slot, then check out as a guest.</p>
            </li>
            <li class="step reveal">
                <span class="step-number">4</span>
                <h3>We deliver and set up</h3>
                <p>Track your order online. We arrive early and clear up after.</p>
            </li>
        </ol>
    </div>
</section>

<!-- Feature -->
<section class="section section-alt">
    <div class="container feature">
        <img class="feature-image reveal" src="images/feature-fresh.jpg"
             alt="Steaming plates of stir-fried vegetables, tofu and rice" loading="lazy">
        <div class="feature-text reveal">
            <h2>Cooked this morning. Not last night.</h2>
            <p class="lead">Every order is cooked fresh on the day of your event and kept hot in our warmers until the last guest is served.</p>
            <ul class="checklist">
                <li>Chafing dishes, serving ware and table set-up included</li>
                <li>Free delivery for orders of $<?= number_format(FREE_DELIVERY_MIN) ?> and above</li>
                <li>Order status updates sent to your email</li>
            </ul>
        </div>
    </div>
</section>

<!-- Occasions -->
<section class="section">
    <div class="container">
        <div class="section-head reveal">
            <h2>For every occasion.</h2>
        </div>
        <div class="occasion-grid">
            <?php foreach ($occasions as $occ): ?>
                <figure class="occasion reveal">
                    <img src="<?= e($occ['image']) ?>" alt="<?= e($occ['alt']) ?>" loading="lazy">
                    <figcaption>
                        <h3><?= e($occ['title']) ?></h3>
                        <p><?= e($occ['text']) ?></p>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Testimonial -->
<section class="section section-alt">
    <blockquote class="container quote reveal">
        <p>&ldquo;Eighty guests, zero stress. The food arrived hot, the set-up was spotless, and everyone asked who catered.&rdquo;</p>
        <footer>Sample review: office year-end party, 80 guests</footer>
    </blockquote>
</section>

<!-- Call to action -->
<section class="section cta">
    <div class="container reveal">
        <h2>Planning an event?</h2>
        <p class="lead">Order today for delivery as early as <strong><?= e($earliest->format('l, j F')) ?></strong>.</p>
        <a class="btn btn-primary" href="buffets.php">Start your order</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
