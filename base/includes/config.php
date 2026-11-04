<?php
// Site-wide settings shared by every page.
// Business rules live here so pages, validation and checkout all agree.

date_default_timezone_set('Asia/Singapore');
session_start();

define('SITE_NAME', 'Makan & Co.');          // placeholder brand name
define('SITE_TAGLINE', 'Buffet catering, made effortless.');
define('LEAD_DAYS', 3);                      // orders need this many days' notice
define('MAX_DAYS_AHEAD', 180);               // and can be placed up to ~6 months ahead
define('MAX_GUESTS', 500);
define('DELIVERY_FEE', 30.00);               // flat delivery + set-up fee (SGD)
define('FREE_DELIVERY_MIN', 500.00);         // orders at or above this ship free
define('CONTACT_PHONE', '+65 6123 4567');
define('CONTACT_EMAIL', 'makanco@localhost');

// Database. XAMPP defaults: user root, no password.
// 127.0.0.1 (not "localhost") makes PHP connect over TCP, which works on Mac and Windows.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'makanco');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Main navigation: file => label
$navItems = [
    'index.php'   => 'Home',
    'buffets.php' => 'Buffets',
    'track.php'   => 'Track Order',
    'about.php'   => 'About',
    'contact.php' => 'Contact',
];

// Delivery time slots offered at checkout.
$timeSlots = [
    '11:00 - 12:00',
    '12:00 - 13:00',
    '15:00 - 16:00',
    '18:00 - 19:00',
];

// Order statuses in the order they normally happen, with customer-facing labels.
$statusLabels = [
    'pending_payment'  => 'Awaiting payment',
    'payment_failed'   => 'Payment failed',
    'confirmed'        => 'Confirmed',
    'preparing'        => 'Preparing',
    'out_for_delivery' => 'Out for delivery',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
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
    return new DateTime('today +' . LEAD_DAYS . ' days');
}

// Latest date a new order can be delivered.
function latestDeliveryDate()
{
    return new DateTime('today +' . MAX_DAYS_AHEAD . ' days');
}

// Number of packages in the guest's cart (stored in the session).
function cartCount()
{
    return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}

require_once __DIR__ . '/functions.php';
