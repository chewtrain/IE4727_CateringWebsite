<?php
// Site-wide settings shared by every page.
// Business rules live here so pages, validation and checkout all agree.

session_start();

define('SITE_NAME', 'Makan & Co.');          // placeholder brand name
define('SITE_TAGLINE', 'Buffet catering, made effortless.');
define('LEAD_DAYS', 3);                      // orders need this many days' notice
define('DELIVERY_FEE', 30.00);               // flat delivery + set-up fee (SGD)
define('FREE_DELIVERY_MIN', 500.00);         // orders at or above this ship free
define('CONTACT_PHONE', '+65 6123 4567');
define('CONTACT_EMAIL', 'orders@makanco.local');

// Main navigation: file => label
$navItems = [
    'index.php'   => 'Home',
    'buffets.php' => 'Buffets',
    'track.php'   => 'Track Order',
    'about.php'   => 'About',
    'contact.php' => 'Contact',
];

// Escape output for HTML.
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Format a price in Singapore dollars, e.g. $16.80
function money($amount)
{
    return '$' . number_format((float) $amount, 2);
}

// Earliest date a new order can be delivered.
function earliestDeliveryDate()
{
    return new DateTime('+' . LEAD_DAYS . ' days', new DateTimeZone('Asia/Singapore'));
}

// Number of packages in the guest's cart (stored in the session).
function cartCount()
{
    return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}
