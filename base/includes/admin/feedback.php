<?php
// Staff view: customer feedback, newest first, with the rating breakdown.
defined('IN_ADMIN') or exit;

$feedback = db()->query('SELECT * FROM feedback ORDER BY created_at DESC')->fetchAll();
$breakdown = [];
foreach (db()->query('SELECT rating, COUNT(*) AS n FROM feedback GROUP BY rating') as $row) {
    $breakdown[(int) $row['rating']] = (int) $row['n'];
}

$pageTitle = 'Feedback | Staff';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">
        <div class="admin-head">
            <h1>Feedback</h1>
            <p class="muted">
                <?php for ($r = 5; $r >= 1; $r--): ?>
                    <?= $r ?>&#9733; <?= isset($breakdown[$r]) ? $breakdown[$r] : 0 ?><?= $r > 1 ? ' &middot; ' : '' ?>
                <?php endfor; ?>
            </p>
        </div>

        <div class="table-wrap">
            <table class="table admin-table">
                <thead>
                    <tr><th scope="col">Received</th><th scope="col">From</th><th scope="col">Rating</th><th scope="col">Message</th></tr>
                </thead>
                <tbody>
                    <?php if (!$feedback): ?><tr><td colspan="4" class="empty">No feedback yet.</td></tr><?php endif; ?>
                    <?php foreach ($feedback as $item): ?>
                        <tr>
                            <td><?= e(date('j M Y', strtotime($item['created_at']))) ?></td>
                            <td>
                                <?= e($item['name']) ?><br><span class="muted"><?= e($item['email']) ?></span>
                                <?php if ($item['order_ref']): ?><br><span class="muted"><?= e($item['order_ref']) ?></span><?php endif; ?>
                            </td>
                            <td class="stars" aria-label="<?= (int) $item['rating'] ?> out of 5"><?= str_repeat('&#9733;', (int) $item['rating']) ?><span class="stars-off"><?= str_repeat('&#9733;', 5 - (int) $item['rating']) ?></span></td>
                            <td class="message"><?= nl2br(e($item['message'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
