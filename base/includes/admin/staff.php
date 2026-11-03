<?php
// Staff view: staff accounts. Add an account, change a password, delete an account.
// You cannot delete yourself, so there is always at least one account.
defined('IN_ADMIN') or exit;

$pdo = db();
$addErrors = [];
$newUsername = '';

if (isPost()) {
    checkCsrf();
    $action = postValue('action');

    if ($action === 'add_staff') {
        $newUsername = strtolower(postValue('username'));
        $password = postValue('password');

        if (!preg_match('/^[a-z0-9_.]{3,40}$/', $newUsername)) {
            $addErrors[] = 'Usernames are 3 to 40 lowercase letters, numbers, dots or underscores.';
        }
        if (strlen($password) < 8) {
            $addErrors[] = 'Passwords need at least 8 characters.';
        } elseif ($password !== postValue('password_confirm')) {
            $addErrors[] = 'The two passwords do not match.';
        }
        if (!$addErrors) {
            try {
                $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
                    ->execute([$newUsername, password_hash($password, PASSWORD_DEFAULT)]);
                flash('success', 'Account ' . $newUsername . ' was created.');
                redirect('admin.php?view=staff');
            } catch (PDOException $ex) {
                $addErrors[] = 'That username is taken.';
            }
        }
    }

    if ($action === 'change_password') {
        $password = postValue('password');
        if (strlen($password) < 8) {
            flash('error', 'Passwords need at least 8 characters. Nothing was changed.');
        } else {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) postValue('admin_id')]);
            flash('success', 'Password changed.');
        }
        redirect('admin.php?view=staff');
    }

    if ($action === 'delete_staff') {
        $targetId = (int) postValue('admin_id');
        if ($targetId === $_SESSION['admin_id']) {
            flash('error', 'You cannot delete the account you are signed in with.');
        } else {
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$targetId]);
            flash('success', 'Account deleted.');
        }
        redirect('admin.php?view=staff');
    }
}

$staff = $pdo->query('SELECT id, username, created_at FROM admins ORDER BY username')->fetchAll();

$pageTitle = 'Staff | Staff';
$adminHeading = 'Staff accounts';
$adminIntro = 'Who can sign in to manage orders and menus. Passwords are stored as one-way hashes.';
$pageScripts = ['js/forms.js'];
require __DIR__ . '/top.php';
?>

<section class="section-tight">
    <div class="container admin-split">
        <div class="table-wrap">
            <table class="table admin-table">
                <thead>
                    <tr><th scope="col">Username</th><th scope="col">Since</th><th scope="col">New password</th><th scope="col"><span class="sr-only">Delete</span></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                        <tr>
                            <td><strong><?= e($member['username']) ?></strong><?= (int) $member['id'] === $_SESSION['admin_id'] ? ' <span class="muted">(you)</span>' : '' ?></td>
                            <td class="muted"><?= e(date('j M Y', strtotime($member['created_at']))) ?></td>
                            <td>
                                <form class="inline-form" method="post" action="admin.php?view=staff">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="change_password">
                                    <input type="hidden" name="admin_id" value="<?= (int) $member['id'] ?>">
                                    <input type="password" name="password" required minlength="8" autocomplete="new-password"
                                           placeholder="At least 8 characters" aria-label="New password for <?= e($member['username']) ?>">
                                    <button class="btn btn-small btn-secondary" type="submit">Change</button>
                                </form>
                            </td>
                            <td>
                                <?php if ((int) $member['id'] !== $_SESSION['admin_id']): ?>
                                    <form method="post" action="admin.php?view=staff" data-confirm="Delete the account <?= e($member['username']) ?>?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_staff">
                                        <input type="hidden" name="admin_id" value="<?= (int) $member['id'] ?>">
                                        <button class="link-button link-danger" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form class="summary" method="post" action="admin.php?view=staff" novalidate data-validate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_staff">
            <h2>Add an account</h2>
            <?php if ($addErrors): ?>
                <div class="alert alert-error"><?php foreach ($addErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div>
            <?php endif; ?>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="40" autocomplete="off"
                       pattern="[A-Za-z0-9_.]{3,40}" data-pattern-message="Use letters, numbers, dots or underscores."
                       value="<?= e($newUsername) ?>">
                <p class="field-error" id="username-error" hidden></p>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <p class="field-error" id="password-error" hidden></p>
            </div>
            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
                       autocomplete="new-password" data-match="password">
                <p class="field-error" id="password_confirm-error" hidden></p>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../footer.php'; ?>
