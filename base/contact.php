<?php
require_once __DIR__ . '/includes/config.php';

$values = ['name' => '', 'email' => '', 'order_ref' => strtoupper(getValue('ref')), 'rating' => '', 'message' => ''];
$errors = [];

if (isPost()) {
    checkCsrf();
    foreach (array_keys($values) as $field) {
        $values[$field] = postValue($field);
    }
    $values['email'] = strtolower($values['email']);
    $values['order_ref'] = strtoupper($values['order_ref']);

    if (!validName($values['name'])) {
        $errors['name'] = 'Enter your name using letters only.';
    }
    if (!validEmail($values['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($values['order_ref'] !== '') {
        if (!validOrderRef($values['order_ref'])) {
            $errors['order_ref'] = 'Order numbers look like MC-7K3QX2.';
        } elseif (!getOrderByRef($values['order_ref'])) {
            $errors['order_ref'] = 'We could not find that order number.';
        }
    }
    if (!in_array($values['rating'], ['1', '2', '3', '4', '5'], true)) {
        $errors['rating'] = 'Choose a rating from 1 to 5.';
    }
    $length = strlen($values['message']);
    if ($length < 10 || $length > 1000) {
        $errors['message'] = 'Write between 10 and 1000 characters.';
    }

    if (!$errors) {
        db()->prepare('INSERT INTO feedback (name, email, order_ref, rating, message) VALUES (?, ?, ?, ?, ?)')
            ->execute([
                $values['name'], $values['email'],
                $values['order_ref'] !== '' ? $values['order_ref'] : null,
                (int) $values['rating'], $values['message'],
            ]);

        sendEmail($values['email'], 'Thanks for your feedback',
            "Hi {$values['name']},\n\nThank you for taking the time to tell us how we did. "
            . "We read every message, and if you asked a question we will reply within one working day.\n\n"
            . "Your message:\n{$values['message']}\n\n" . SITE_NAME . "\n");

        flash('success', 'Thank you, ' . $values['name'] . '. Your feedback was sent.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contact and Feedback';
$pageScripts = ['js/forms.js'];
require __DIR__ . '/includes/header.php';

function contactError($errors, $field)
{
    $hidden = isset($errors[$field]) ? '' : ' hidden';
    $text = isset($errors[$field]) ? e($errors[$field]) : '';
    return '<p class="field-error" id="' . $field . '-error"' . $hidden . '>' . $text . '</p>';
}
?>

<section class="page-head">
    <div class="container">
        <h1>Get in touch.</h1>
        <p class="lead">Questions about an order, or feedback on your last buffet? We would love to hear from you.</p>
    </div>
</section>

<section class="section-tight">
    <div class="container checkout-grid">
        <form class="form-card" method="post" action="contact.php" novalidate data-validate>
            <?= csrfField() ?>
            <h2>Send us feedback</h2>

            <div class="form-grid">
                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required maxlength="60" autocomplete="name"
                           pattern="[A-Za-z][A-Za-z .'\/\-]{1,59}" data-pattern-message="Use letters, spaces, apostrophes and hyphens only."
                           value="<?= e($values['name']) ?>">
                    <?= contactError($errors, 'name') ?>
                </div>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required maxlength="120" autocomplete="email"
                           value="<?= e($values['email']) ?>">
                    <?= contactError($errors, 'email') ?>
                </div>
                <div class="field field-full<?= isset($errors['order_ref']) ? ' has-error' : '' ?>">
                    <label for="order_ref">Order number <span class="muted">(optional)</span></label>
                    <input type="text" id="order_ref" name="order_ref" maxlength="9" placeholder="MC-7K3QX2"
                           pattern="[Mm][Cc]-[A-Za-z0-9]{6}" data-pattern-message="Order numbers look like MC-7K3QX2."
                           value="<?= e($values['order_ref']) ?>">
                    <?= contactError($errors, 'order_ref') ?>
                </div>

                <fieldset class="field field-full<?= isset($errors['rating']) ? ' has-error' : '' ?>">
                    <legend>How did we do?</legend>
                    <div class="rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>" required
                                   <?= $values['rating'] === (string) $i ? 'checked' : '' ?>>
                            <label for="rating-<?= $i ?>" title="<?= $i ?> out of 5"><span class="sr-only"><?= $i ?> out of 5</span></label>
                        <?php endfor; ?>
                    </div>
                    <?= contactError($errors, 'rating') ?>
                </fieldset>

                <div class="field field-full<?= isset($errors['message']) ? ' has-error' : '' ?>">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" required minlength="10" maxlength="1000" data-counter><?= e($values['message']) ?></textarea>
                    <p class="field-hint"><span class="char-count">0</span>/1000</p>
                    <?= contactError($errors, 'message') ?>
                </div>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Send feedback</button>
        </form>

        <aside class="summary">
            <h2>Contact</h2>
            <dl class="detail-list">
                <dt>Phone</dt><dd><?= e(CONTACT_PHONE) ?></dd>
                <dt>Email</dt><dd><?= e(CONTACT_EMAIL) ?></dd>
                <dt>Hours</dt><dd>Daily, 9am to 7pm</dd>
                <dt>Kitchen</dt><dd>Tai Seng, Singapore<br><span class="muted">Collection by appointment</span></dd>
            </dl>
            <p class="field-hint">Already ordered? <a href="track.php">Track your order</a> for the latest status.</p>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
