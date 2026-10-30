<?php
// Shared page header. Set $pageTitle before including this file.
require_once __DIR__ . '/config.php';

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$fullTitle = isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(SITE_NAME . ' - ' . SITE_TAGLINE) ?>">
    <title><?= e($fullTitle) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="siteHeader">
    <nav class="nav container" aria-label="Main">
        <a class="nav-logo" href="index.php"><?= e(SITE_NAME) ?></a>

        <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="navLinks">
            <span class="sr-only">Menu</span>
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
        </button>

        <ul class="nav-links" id="navLinks">
            <?php foreach ($navItems as $file => $label): ?>
                <li>
                    <a href="<?= e($file) ?>"<?= $file === $currentPage ? ' class="active" aria-current="page"' : '' ?>>
                        <?= e($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <a class="nav-cart" href="cart.php" aria-label="Cart, <?= cartCount() ?> item(s)">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                <path d="M6 7h12l-1 13H7L6 7Zm3 0a3 3 0 0 1 6 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
            <?php if (cartCount() > 0): ?>
                <span class="nav-cart-count"><?= cartCount() ?></span>
            <?php endif; ?>
        </a>
    </nav>
</header>

<main id="main">
<?php $flashes = takeFlashes(); ?>
<?php if ($flashes): ?>
    <div class="container flash-stack">
        <?php foreach ($flashes as $flash): ?>
            <p class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
