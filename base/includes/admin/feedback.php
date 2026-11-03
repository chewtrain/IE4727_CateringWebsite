<?php
// Staff view: customer feedback, newest first, with the rating breakdown.
// Staff can delete feedback (e.g. spam). It cannot be edited: it is the customer's own words.
defined('IN_ADMIN') or exit;

if (isPost() && postValue('action') === 'delete_feedback') {
    checkCsrf();
    $stmt = db()->prepare('DELETE FROM feedback WHERE id = ?');
    $stmt->execute([(int) postValue('feedback_id')]);
    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Feedback deleted.' : 'That feedback was already deleted.');
    redirect('admin.php?view=feedback');
}

$feedback = db()->query('SELECT * FROM feedback ORDER BY created_at DESC')->fetchAll();
$breakdown = [];
foreach (db()->query('SELECT rating, COUNT(*) AS n FROM feedback GROUP BY rating') as $row) {
    $breakdown[(int) $row['rating']] = (int) $row['n'];
}

$pageTitle = 'Feedback | Staff';
$adminHeading = 'Feedback';
$adminIntro = 'What customers told us through the contact page.';
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container">
        <p class="muted rating-summary">
            <?php for ($r = 5; $r >= 1; $r--): ?>
                <?= $r ?>&#9733; <?= isset($breakdown[$r]) ? $breakdown[$r] : 0 ?><?= $r > 1 ? ' &middot; ' : '' ?>
            <?php endfor; ?>
        </p>

        <div class="table-wrap">
            <table class="table admin-table">
                <thead>
                    <tr><th scope="col">Received</th><th scope="col">From</th><th scope="col">Rating</th><th scope="col">Message</th><th scope="col"><span class="sr-only">Delete</span></th></tr>
                </thead>
                <tbody>
                    <?php if (!$feedback): ?><tr><td colspan="5" class="empty">No feedback yet.</td></tr><?php endif; ?>
                    <?php foreach ($feedback as $item): ?>
                        <tr>
                            <td><?= e(date('j M Y', strtotime($item['created_at']))) ?></td>
                            <td>
                                <?= e($item['name']) ?><br><span class="muted"><?= e($item['email']) ?></span>
                                <?php if ($item['order_ref']): ?><br><span class="muted"><?= e($item['order_ref']) ?></span><?php endif; ?>
                            </td>
                            <td class="stars" aria-label="<?= (int) $item['rating'] ?> out of 5"><?= str_repeat('&#9733;', (int) $item['rating']) ?><span class="stars-off"><?= str_repeat('&#9733;', 5 - (int) $item['rating']) ?></span></td>
                            <td class="message"><?= nl2br(e($item['message'])) ?></td>
                            <td>
                                <form method="post" action="admin.php?view=feedback" data-confirm="Delete this feedback from <?= e($item['name']) ?>? This cannot be undone.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_feedback">
                                    <input type="hidden" name="feedback_id" value="<?= (int) $item['id'] ?>">
                                    <button class="link-button link-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
