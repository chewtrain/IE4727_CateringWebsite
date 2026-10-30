<?php
// Staff area. One page, several views chosen with ?view=..., so the site stays
// within the page limit. Each view's logic and markup is in includes/admin/.
require_once __DIR__ . '/includes/config.php';

define('IN_ADMIN', true);   // admin views refuse to run without this

$adminViews = [
    'orders'   => 'Orders',
    'menu'     => 'Menu',
    'reports'  => 'Reports',
    'feedback' => 'Feedback',
    'emails'   => 'Email log',
];

// Log out.
if (isPost() && postValue('action') === 'logout') {
    checkCsrf();
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    session_regenerate_id(true);
    flash('success', 'You have been logged out.');
    redirect('admin.php');
}

// Not logged in: show and handle the login form.
if (empty($_SESSION['admin_id'])) {
    $loginError = '';
    $username = '';

    if (isPost()) {
        checkCsrf();
        $username = postValue('username');
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify(postValue('password'), $admin['password_hash'])) {
            session_regenerate_id(true);   // new session id after login
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            redirect('admin.php');
        }
        $loginError = 'Wrong username or password.';
    }

    $pageTitle = 'Staff Login';
    $pageScripts = ['js/forms.js'];
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/admin/login.php';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$view = getValue('view');
if ($view === 'order') {
    $activeTab = 'orders';          // single order detail, under the Orders tab
} elseif (isset($adminViews[$view])) {
    $activeTab = $view;
} else {
    $view = $activeTab = 'orders';
}

require __DIR__ . '/includes/admin/' . $view . '.php';
