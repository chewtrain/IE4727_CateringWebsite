</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <h2 class="footer-heading">Order</h2>
                <ul>
                    <li><a href="buffets.php">Buffet packages</a></li>
                    <li><a href="cart.php">Your cart</a></li>
                    <li><a href="track.php">Track an order</a></li>
                </ul>
            </div>
            <div>
                <h2 class="footer-heading">Help</h2>
                <ul>
                    <li><a href="index.php#how-it-works">How it works</a></li>
                    <li><a href="about.php#delivery">Delivery &amp; set-up</a></li>
                    <li><a href="contact.php">Feedback</a></li>
                </ul>
            </div>
            <div>
                <h2 class="footer-heading">Company</h2>
                <ul>
                    <li><a href="about.php">About us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="admin.php">Staff login</a></li>
                </ul>
            </div>
            <div>
                <h2 class="footer-heading">Get in touch</h2>
                <p><?= e(CONTACT_PHONE) ?><br><?= e(CONTACT_EMAIL) ?></p>
                <p>Daily, 9am to 7pm</p>
            </div>
        </div>

        <div class="footer-legal">
            <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> IE4727 student project. Not a real business.</p>
            <p>Food photography from Pexels.</p>
        </div>
    </div>
</footer>

<script src="js/main.js"></script>
<?php foreach (isset($pageScripts) ? $pageScripts : [] as $script): ?>
<script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
