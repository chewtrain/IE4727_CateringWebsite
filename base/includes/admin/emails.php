<?php
// Staff view: every email the site has sent, so the demo works even without a mail server.
defined('IN_ADMIN') or exit;

$emails = db()->query(
    'SELECT e.*, o.order_ref FROM email_log e LEFT JOIN orders o ON o.id = e.order_id
     ORDER BY e.created_at DESC, e.id DESC LIMIT 50'
)->fetchAll();

$pageTitle = 'Email Log | Staff';
$adminHeading = 'Email log';
$adminIntro = 'The 50 most recent emails sent by the site. With Mailpit running, they also appear at localhost:8025.';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">

        <?php if (!$emails): ?>
            <p class="muted">No emails sent yet. Place an order or update an order's status to send one.</p>
        <?php endif; ?>

        <div class="email-list">
            <?php foreach ($emails as $mail): ?>
                <details class="email">
                    <summary>
                        <span class="badge <?= $mail['sent_ok'] ? 'badge-delivered' : 'badge-payment_failed' ?>"><?= $mail['sent_ok'] ? 'Sent' : 'Not sent' ?></span>
                        <strong><?= e($mail['subject']) ?></strong>
                        <span class="muted">to <?= e($mail['to_email']) ?> &middot; <?= e(date('j M, g:i a', strtotime($mail['created_at']))) ?></span>
                    </summary>
                    <pre><?= e($mail['body']) ?></pre>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
