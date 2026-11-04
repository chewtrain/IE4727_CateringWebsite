<?php
// Staff area. One page, several views chosen with ?view=..., 
//  Each view's logic and markup is in includes/admin/.
require_once __DIR__ . '/includes/config.php';

define('IN_ADMIN', true);   // admin views refuse to run without this

// Tabs: view => [label, banner image]
$adminViews = [
    'orders'   => ['Orders',    'images/occasion-corporate.jpg'],
    'packages' => ['Packages',  'images/pkg-nusantara.jpg'],
    'dishes'   => ['Dishes',    'images/feature-fresh.jpg'],
    'reports'  => ['Reports',   'images/hero-spread.jpg'],
    'feedback' => ['Feedback',  'images/occasion-family.jpg'],
    'emails'   => ['Email log', 'images/pkg-dim-sum-tea.jpg'],
    'staff'    => ['Staff',     'images/occasion-celebration.jpg'],
];

// Detail and edit screens, shown under their parent tab.
$subViews = [
    'order'   => 'orders',
    'package' => 'packages',
    'dish'    => 'dishes',
];

// Error message under a form field in the staff forms.
function adminFieldError($errors, $field)
{
    return isset($errors[$field]) ? '<p class="field-error">' . e($errors[$field]) . '</p>' : '';
}

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

// A staff account deleted while logged in loses access straight away.
$stillExists = db()->prepare('SELECT COUNT(*) FROM admins WHERE id = ?');
$stillExists->execute([$_SESSION['admin_id']]);
if (!$stillExists->fetchColumn()) {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    redirect('admin.php');
}

$view = getValue('view');
if (isset($subViews[$view])) {
    $activeTab = $subViews[$view];
} elseif (isset($adminViews[$view])) {
    $activeTab = $view;
} else {
    $view = $activeTab = 'orders';
}

require __DIR__ . '/includes/admin/' . $view . '.php';
