<?php defined('IN_ADMIN') or exit; ?>
<section class="page-head">
    <div class="container">
        <h1>Staff login.</h1>
        <p class="lead">Manage orders, menus and reports.</p>
    </div>
</section>

<section class="section-tight">
    <div class="container narrow">
        <form class="form-card" method="post" action="admin.php" novalidate data-validate>
            <?= csrfField() ?>
            <?php if ($loginError): ?>
                <p class="alert alert-error" role="alert"><?= e($loginError) ?></p>
            <?php endif; ?>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required maxlength="40" autocomplete="username"
                       value="<?= e($username) ?>">
                <p class="field-error" id="username-error" hidden></p>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6" autocomplete="current-password">
                <p class="field-error" id="password-error" hidden></p>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>
    </div>
</section>
