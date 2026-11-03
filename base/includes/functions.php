<?php
// Shared helpers: database, form security, flash messages, cart, validation, email.
// Loaded by config.php, so every page has these available.

// ---------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------

// One shared PDO connection per request.
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('<p style="font-family:sans-serif;padding:2em">Cannot connect to the database. '
                . 'Check that MySQL is running and that db/schema.sql and db/seed.sql have been imported.</p>');
        }
    }
    return $pdo;
}

// ---------------------------------------------------------------------
// Requests, redirects and flash messages
// ---------------------------------------------------------------------

function isPost()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Trimmed string from $_POST (or '' when missing).
function postValue($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Trimmed string from $_GET (or '' when missing).
function getValue($key)
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

// Store a message to show on the next page load.
function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Return and clear all pending flash messages.
function takeFlashes()
{
    $messages = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $messages;
}

// Absolute URL of a page in this site, built from server variables.
function siteUrl($page)
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/' . $page;
}

// ---------------------------------------------------------------------
// CSRF protection: every POST form carries a token tied to the session
// ---------------------------------------------------------------------

function csrfToken()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfField()
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

// Stop the request if the form's token does not match.
function checkCsrf()
{
    if (!hash_equals(csrfToken(), postValue('csrf'))) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------------------
// Packages and menus
// ---------------------------------------------------------------------

function getPackage($id)
{
    $stmt = db()->prepare('SELECT * FROM packages WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// A package's menu: categories in order, each with its pick rule and active dishes.
function getPackageMenu($packageId)
{
    $stmt = db()->prepare(
        'SELECT c.id AS category_id, c.name AS category_name, r.choose_count,
                d.id AS dish_id, d.name AS dish_name, d.description, d.is_vegetarian, d.is_spicy
         FROM package_rules r
         JOIN categories c      ON c.id = r.category_id
         JOIN package_dishes pd ON pd.package_id = r.package_id
         JOIN dishes d          ON d.id = pd.dish_id AND d.category_id = c.id
         WHERE r.package_id = ? AND d.is_active = 1
         ORDER BY c.sort_order, d.name'
    );
    $stmt->execute([$packageId]);

    $menu = [];
    foreach ($stmt as $row) {
        $catId = $row['category_id'];
        if (!isset($menu[$catId])) {
            $menu[$catId] = [
                'name'   => $row['category_name'],
                'choose' => (int) $row['choose_count'],
                'dishes' => [],
            ];
        }
        $menu[$catId]['dishes'][$row['dish_id']] = $row;
    }
    return $menu;
}

// Check chosen dish IDs against a package menu. Returns a list of error messages.
function validateDishSelection($menu, $dishIds)
{
    $errors = [];
    $remaining = array_flip($dishIds);

    foreach ($menu as $category) {
        $picked = 0;
        foreach ($category['dishes'] as $dishId => $dish) {
            if (isset($remaining[$dishId])) {
                $picked++;
                unset($remaining[$dishId]);
            }
        }
        if ($picked !== $category['choose']) {
            $errors[] = 'Choose exactly ' . $category['choose'] . ' from ' . $category['name']
                . ' (you chose ' . $picked . ').';
        }
    }
    if (count($remaining) > 0) {
        $errors[] = 'Some of the dishes you chose are not part of this package.';
    }
    return $errors;
}

// ---------------------------------------------------------------------
// Cart (kept in the session; prices always re-read from the database)
// ---------------------------------------------------------------------

function cartAdd($packageId, $guests, $dishIds)
{
    $_SESSION['cart'][] = [
        'package_id' => (int) $packageId,
        'guests'     => (int) $guests,
        'dishes'     => array_map('intval', $dishIds),
    ];
}

function cartClear()
{
    $_SESSION['cart'] = [];
}

function deliveryFee($subtotal)
{
    return ($subtotal >= FREE_DELIVERY_MIN || $subtotal == 0) ? 0.00 : DELIVERY_FEE;
}

// Cart lines with package details, chosen dishes and totals.
function cartDetails()
{
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    $lines = [];
    $subtotal = 0;
    $valid = true;

    $dishStmt = db()->prepare(
        'SELECT d.id, d.name, c.name AS category_name
         FROM dishes d JOIN categories c ON c.id = d.category_id
         WHERE d.id = ? AND d.is_active = 1'
    );

    foreach ($cart as $index => $item) {
        $package = getPackage($item['package_id']);
        if (!$package) {
            $valid = false;
            $lines[$index] = ['missing' => true, 'guests' => $item['guests']];
            continue;
        }

        $dishes = [];
        foreach ($item['dishes'] as $dishId) {
            $dishStmt->execute([$dishId]);
            $dish = $dishStmt->fetch();
            if ($dish) {
                $dishes[] = $dish;
            } else {
                $valid = false;   // dish removed from the menu since it was added
            }
        }

        $lineTotal = round($package['price_per_pax'] * $item['guests'], 2);
        $subtotal += $lineTotal;
        $lines[$index] = [
            'missing'    => false,
            'package'    => $package,
            'guests'     => $item['guests'],
            'dishes'     => $dishes,
            'line_total' => $lineTotal,
        ];
    }

    $fee = deliveryFee($subtotal);
    return [
        'lines'    => $lines,
        'subtotal' => $subtotal,
        'delivery' => $fee,
        'total'    => $subtotal + $fee,
        'valid'    => $valid,
    ];
}

// ---------------------------------------------------------------------
// Orders
// ---------------------------------------------------------------------

// Random reference like MC-7K3QX2 (no 0/O or 1/I to avoid confusion).
function generateOrderRef()
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $check = db()->prepare('SELECT COUNT(*) FROM orders WHERE order_ref = ?');
    do {
        $ref = 'MC-';
        for ($i = 0; $i < 6; $i++) {
            $ref .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $check->execute([$ref]);
    } while ($check->fetchColumn() > 0);
    return $ref;
}

function getOrderByRef($ref)
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_ref = ?');
    $stmt->execute([$ref]);
    return $stmt->fetch();
}

// Items of an order, each with its chosen dishes.
function getOrderItems($orderId)
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    $dishStmt = db()->prepare(
        'SELECT dish_name, category_name FROM order_item_dishes WHERE order_item_id = ?'
    );
    foreach ($items as $i => $item) {
        $dishStmt->execute([$item['id']]);
        $items[$i]['dishes'] = $dishStmt->fetchAll();
    }
    return $items;
}

// Photo for an order: its first package's photo, or a general one if that
// package has since been deleted.
function getOrderImage($orderId)
{
    $stmt = db()->prepare(
        'SELECT p.image, p.image_alt FROM order_items i JOIN packages p ON p.id = i.package_id
         WHERE i.order_id = ? ORDER BY i.id LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $photo = $stmt->fetch();
    return $photo ?: ['image' => 'images/hero-spread.jpg', 'image_alt' => 'A table of Asian dishes seen from above'];
}

function getOrderHistory($orderId)
{
    $stmt = db()->prepare(
        'SELECT status, note, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at, id'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

// Change an order's status and record it in the history.
function setOrderStatus($orderId, $status, $note)
{
    $pdo = db();
    $sql = 'UPDATE orders SET status = ?' . ($status === 'confirmed' ? ', paid_at = COALESCE(paid_at, NOW())' : '')
         . ' WHERE id = ?';
    $pdo->prepare($sql)->execute([$status, $orderId]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)')
        ->execute([$orderId, $status, $note]);
}

function statusLabel($status)
{
    global $statusLabels;
    return isset($statusLabels[$status]) ? $statusLabels[$status] : $status;
}

// Remember orders this browser placed or looked up, so it can view them again.
function rememberOrder($ref)
{
    $_SESSION['my_orders'][$ref] = true;
}

function ownsOrder($ref)
{
    return !empty($_SESSION['my_orders'][$ref]);
}

// ---------------------------------------------------------------------
// Validation (server side; the same rules are checked in JavaScript)
// ---------------------------------------------------------------------

function validName($name)
{
    return (bool) preg_match("/^[A-Za-z][A-Za-z .'\\/-]{1,59}$/", $name);
}

function validEmail($email)
{
    return strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Singapore numbers: 8 digits starting with 6, 8 or 9.
function validPhone($phone)
{
    return (bool) preg_match('/^[689]\d{7}$/', $phone);
}

// Singapore postal codes: 6 digits; the first two (the sector) run 01-82, except 74.
function validPostalCode($code)
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $sector = (int) substr($code, 0, 2);
    return $sector >= 1 && $sector <= 82 && $sector !== 74;
}

function validOrderRef($ref)
{
    return (bool) preg_match('/^MC-[A-Z0-9]{6}$/', $ref);
}

// Event date must be a real date between the lead time and the booking horizon.
function validEventDate($value)
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return false;
    }
    return $date >= earliestDeliveryDate() && $date <= latestDeliveryDate();
}

// ---------------------------------------------------------------------
// Email: sent with PHP mail() and always copied to the email_log table
// ---------------------------------------------------------------------

function sendEmail($to, $subject, $body, $orderId = null)
{
    $headers = 'From: ' . SITE_NAME . ' <' . CONTACT_EMAIL . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    $sent = @mail($to, $subject, $body, $headers);

    db()->prepare('INSERT INTO email_log (order_id, to_email, subject, body, sent_ok) VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, $to, $subject, $body, $sent ? 1 : 0]);
    return $sent;
}

// Plain-text summary of an order, used in emails.
function orderSummaryText($order, $items)
{
    $text = "Order: {$order['order_ref']}\n"
          . 'Delivery: ' . date('l, j F Y', strtotime($order['event_date'])) . ", {$order['time_slot']}\n"
          . "Address: {$order['address']}, Singapore {$order['postal_code']}\n\n";
    foreach ($items as $item) {
        $text .= "{$item['package_name']} x {$item['guests']} guests: " . money($item['line_total']) . "\n";
        foreach ($item['dishes'] as $dish) {
            $text .= "  - {$dish['dish_name']}\n";
        }
    }
    $text .= "\nSubtotal: " . money($order['subtotal'])
           . "\nDelivery: " . ($order['delivery_fee'] > 0 ? money($order['delivery_fee']) : 'Free')
           . "\nTotal: " . money($order['total']) . "\n";
    return $text;
}

function sendOrderConfirmation($order)
{
    $items = getOrderItems($order['id']);
    $body = "Hi {$order['customer_name']},\n\n"
          . "Thank you for your order. Payment was successful and your buffet is confirmed.\n\n"
          . orderSummaryText($order, $items)
          . "\nTrack your order at any time:\n"
          . siteUrl('track.php?ref=' . urlencode($order['order_ref']) . '&email=' . urlencode($order['email'])) . "\n\n"
          . SITE_NAME . "\n" . CONTACT_PHONE . "\n";
    return sendEmail($order['email'], 'Order confirmed: ' . $order['order_ref'], $body, $order['id']);
}

function sendStatusUpdate($order, $status, $note)
{
    $body = "Hi {$order['customer_name']},\n\n"
          . "Your order {$order['order_ref']} is now: " . statusLabel($status) . ".\n"
          . ($note !== '' ? "\n{$note}\n" : '')
          . "\nDelivery: " . date('l, j F Y', strtotime($order['event_date'])) . ", {$order['time_slot']}\n"
          . "\nTrack your order:\n"
          . siteUrl('track.php?ref=' . urlencode($order['order_ref']) . '&email=' . urlencode($order['email'])) . "\n\n"
          . SITE_NAME . "\n";
    return sendEmail($order['email'], 'Order ' . $order['order_ref'] . ': ' . statusLabel($status), $body, $order['id']);
}
