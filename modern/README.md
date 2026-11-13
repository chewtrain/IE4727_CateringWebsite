# Makan & Co. — modern version

The additional (modern) version of the IE4727 project: a single-page React app
with a Go API. It **shares the base version's `makanco` database**, so:

- orders placed here appear in the PHP staff area (`base/admin.php`), and
- status changes made by staff there show up live on this app's tracking page.

There is no staff area in this version; staff keep using the PHP one.

| Part | Technology | Where |
|---|---|---|
| Front end | React 19, TypeScript, Vite | `web/` |
| Styling | Tailwind CSS 4 (same colours as `base/css/style.css`) | `web/src/index.css` |
| Animation | Motion (`motion/react`), View Transitions | `web/src/components/Motion.tsx` |
| 3D buffet preview | three.js via React Three Fiber + drei, desktop only | `web/src/components/BuffetPreview.tsx` |
| API | Go (standard library router), `go-sql-driver/mysql`, `sqlx` | `api/` |
| Live tracking | Server-Sent Events | `api/events.go` |
| Database | the base version's MySQL/MariaDB `makanco` database | `../db/` |

## Run it

You need the base version's database first (see the main `README.md`: start
MySQL in XAMPP, import `db/schema.sql` then `db/seed.sql`). Then install
**Go 1.24+** and **Node.js 22.22+**.

1. **Start the API** (port 8080):
   ```
   cd modern/api
   go run .
   ```
   It connects to `127.0.0.1:3306` as `root` with no password (XAMPP's default).
   Change that with environment variables if needed (table below).

2. **Start the React app** in a second terminal (port 5173):
   ```
   cd modern/web
   npm install
   npm run dev
   ```
   Open http://localhost:5173. Vite forwards `/api` and `/images` to the Go API.

3. **Optional: email.** Start [Mailpit](https://mailpit.axllent.org/) and read the
   order confirmations at http://localhost:8025. Without it, orders still work and
   every email is saved in the `email_log` table (visible in the PHP staff area).

**One server instead of two:** run `npm run build` in `modern/web`, then `go run .`
in `modern/api`. The API serves the built app at http://localhost:8080 (set
`APP_URL=http://localhost:8080` so email links point there).

### Settings (environment variables for the API)

| Variable | Default | Meaning |
|---|---|---|
| `PORT` | `8080` | API port |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | MySQL / MariaDB server |
| `DB_USER`, `DB_PASS`, `DB_NAME` | `root`, empty, `makanco` | database login |
| `SMTP_ADDR` | `localhost:1025` | Mailpit's SMTP port |
| `APP_URL` | `http://localhost:5173` | used for tracking links in emails |
| `IMAGES_DIR` | `../../base/images` | photos are shared with the base version, not copied |
| `WEB_DIST` | `../web/dist` | built React app, served when present |

Mac/Linux: `DB_PASS=secret go run .` · Windows PowerShell: `$env:DB_PASS="secret"; go run .`

For the React app, `VITE_CLASSIC_URL` (default `http://localhost/makanco/`) sets
where the footer links to the PHP site's About, Contact and staff pages.

## Pages

| Route | What it does |
|---|---|
| `/` | Home: animated hero, featured packages, how it works, occasions |
| `/buffets` | All packages; guest-count estimator, animated sorting, comparison table |
| `/buffets/:id` | Dish picker with live price; 3D buffet line on desktop |
| `/checkout` | Details form → simulated payment (success / declined) → confirmation |
| `/track`, `/track/:ref` | Order lookup, then a live status timeline |

The cart is a slide-out drawer available on every page.

## How it shares the database safely

- The API writes exactly the rows `base/checkout.php` writes, in one transaction:
  `orders`, `order_items`, `order_item_dishes` and the first
  `order_status_history` row, with the same `MC-XXXXXX` reference format.
- Prices are always re-read from the database; the browser's totals are display only.
- Every field is validated again on the server with the same rules and messages
  as the PHP site (`api/validate.go`).
- The business rules (3-day lead time, $30 delivery, free from $500, 500 guests
  maximum, time slots) are copied in `api/rules.go`. **If you change them in
  `base/includes/config.php`, change them there too.** The main README has the
  full list, matching each PHP constant to its Go name:
  [Rules kept in two places](../README.md#rules-kept-in-two-places).
- No tables or columns were added; the base version is untouched.

## Demo: live tracking across both versions

1. Place an order at http://localhost:5173 and choose "Payment succeeds".
2. Click **Track this order live**. The page shows "Live updates on".
3. In another window, log in to the PHP staff area (`admin` / `admin123`), open
   the order and change its status.
4. Within about two seconds the React page updates its timeline and shows a
   "Status updated" banner, without reloading.

## API

| Method and path | Purpose |
|---|---|
| `GET /api/rules` | Business rules and booking window for the forms |
| `GET /api/packages` | Active packages |
| `GET /api/packages/{id}` | One package with its menu and "choose N" rules |
| `POST /api/orders` | Validate, price and save an order (status: awaiting payment) |
| `GET /api/orders/{ref}?email=` | Order details (the email proves ownership, like `track.php`) |
| `POST /api/orders/{ref}/payment` | The payment gateway's answer: `success` or `failed` |
| `GET /api/orders/{ref}/events?email=` | Server-Sent Events stream of order updates |

## Notes

- **3D preview:** drawn entirely in code (no downloaded models or licences). It
  only loads on screens 1024px and wider with WebGL; phones get the dish list
  without downloading three.js.
- **Browser support:** page transitions use the View Transitions API. Browsers
  without it change pages instantly; everything else works the same.
