# Makan & Co. (IE4727 project)

Online buffet catering portal (Theme 1). The base version in `base/` uses only HTML, CSS, JavaScript, PHP and MySQL, as the project guidelines require.

## Run it with XAMPP (Mac or Windows)

1. **Start Apache and MySQL** in the XAMPP control panel.
2. **Create the database.** In phpMyAdmin (http://localhost/phpmyadmin), open the *Import* tab and import `db/schema.sql`, then `db/seed.sql`. From a terminal instead:
   ```
   mysql -u root < db/schema.sql
   mysql -u root < db/seed.sql
   ```
   (On a Mac the client is `/Applications/XAMPP/xamppfiles/bin/mysql`.)
3. **Put the site in `htdocs`.** Copy the `base` folder to XAMPP's `htdocs` and rename it `makanco`, or link it so edits show up immediately:
   - Mac: `ln -s "$PWD/base" /Applications/XAMPP/xamppfiles/htdocs/makanco`
   - Windows (admin prompt): `mklink /D C:\xampp\htdocs\makanco C:\path\to\repo\base`
4. Open http://localhost/makanco/

The database login is in `base/includes/config.php` (XAMPP's default is user `root` with no password).

**Staff area:** http://localhost/makanco/admin.php (also linked in the footer). Username `admin`, password `admin123`.

## Email (optional)

The site sends an email when an order is confirmed, when staff change its status, and when feedback arrives. Every email is also saved to the `email_log` table and shown under *Admin > Email log*, so the demo works without a mail server.

To receive the emails in a real inbox, use [Mailpit](https://mailpit.axllent.org/). It catches all mail locally, so nothing is sent outside your machine, as the brief requires. Start Mailpit, then set one line in XAMPP's `php.ini` and restart Apache:

- Windows: `SMTP=localhost` and `smtp_port=1025`
- Mac: `sendmail_path = "/path/to/mailpit sendmail -t"`

Read the emails at http://localhost:8025.

## Pages

| Page | Purpose |
|---|---|
| `index.php` | Home: featured packages from the database, how ordering works |
| `buffets.php` | All packages (server-generated), sortable, comparison table |
| `package.php` | Dish picker: guest count and "choose N per category" rules |
| `cart.php` | Session cart: change guest numbers, remove packages, live totals |
| `checkout.php` | Guest checkout form (HTML5 + JavaScript + PHP validation) |
| `payment.php` | Stand-in for the third-party payment gateway: success or declined |
| `track.php` | Order lookup by order number and email, status timeline |
| `about.php` | About, delivery and set-up terms, time slot table, FAQ |
| `contact.php` | Contact details and feedback form with star rating |
| `admin.php` | Staff area (one page, tabs chosen with `?view=`): orders, packages, dishes and categories, reports, feedback, email log, staff accounts |

## Business rules

These are set in `base/includes/config.php`:
- Orders need at least **3 days' notice** and can be placed up to 6 months ahead.
- Delivery is **$30**, or free from **$500**.
- Each package has a **minimum number of guests**; the maximum is 500.

## Database

There are 12 tables; see `db/schema.sql`:
- **Menu:** `packages`, `categories`, `dishes`, `package_rules` (how many dishes to choose per category), `package_dishes`.
- **Orders:** `orders`, `order_items`, `order_item_dishes`. Each order copies the names and prices at the time of purchase, so later menu changes never alter past orders.
- **Supporting:** `order_status_history` (drives the tracking timeline), `email_log`, `feedback`, `admins`.

The seed data has 12 fictional sample orders, so the reports aren't empty.

### Create, read, update, delete

Customers check out as guests (no accounts) and are kept informed by email. Staff manage the data from the staff area:

| Table | Create | Read | Update | Delete |
|---|---|---|---|---|
| packages | New package | Buffets, package, staff Packages tab | All fields, "choose N" rules, dishes offered, photo | Yes; past orders keep their copied name and price |
| dishes | New dish | Package menus, staff Dishes tab | Edit form, available/hidden switch | Yes; past orders keep the dish name |
| categories | Add | Dishes tab | Rename, reorder | Only when no dishes use it |
| orders | Checkout | Track page, staff Orders tab | Payment result, staff status changes | Only unpaid or cancelled orders; paid orders are kept for sales records |
| feedback | Contact form | Staff Feedback tab | none (it is the customer's own words) | Yes (e.g. spam) |
| admins | Staff tab | Staff tab | Change password | Yes, except your own account |

Every delete asks for confirmation in the browser and is checked again on the server.
