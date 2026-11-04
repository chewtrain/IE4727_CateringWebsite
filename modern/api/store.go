package main

import (
	"context"
	"crypto/rand"
	"database/sql"
	"errors"
	"fmt"
	"math"
	"math/big"

	"github.com/jmoiron/sqlx"
)

// store holds every query the API runs against the shared makanco database.
// Table and column names are the ones in db/schema.sql; nothing here changes the schema.
type store struct{ db *sqlx.DB }

// ---------------------------------------------------------------------
// Menu
// ---------------------------------------------------------------------

type pkg struct {
	ID          int     `db:"id" json:"id"`
	Name        string  `db:"name" json:"name"`
	Tagline     string  `db:"tagline" json:"tagline"`
	Description string  `db:"description" json:"description"`
	PricePerPax float64 `db:"price_per_pax" json:"pricePerPax"`
	MinPax      int     `db:"min_pax" json:"minPax"`
	Image       string  `db:"image" json:"image"`
	ImageAlt    string  `db:"image_alt" json:"imageAlt"`
	IsFeatured  bool    `db:"is_featured" json:"isFeatured"`
	DishCount   int     `db:"dish_count" json:"dishCount"`
}

const packageColumns = `p.id, p.name, p.tagline, p.description, p.price_per_pax, p.min_pax,
	p.image, p.image_alt, p.is_featured,
	COALESCE((SELECT SUM(r.choose_count) FROM package_rules r WHERE r.package_id = p.id), 0) AS dish_count`

func (s *store) listPackages(ctx context.Context) ([]pkg, error) {
	pkgs := []pkg{}
	err := s.db.SelectContext(ctx, &pkgs,
		`SELECT `+packageColumns+` FROM packages p WHERE p.is_active = 1 ORDER BY p.sort_order, p.id`)
	return pkgs, err
}

// getPackage returns an active package, or (nil, nil) when there is none.
func (s *store) getPackage(ctx context.Context, id int) (*pkg, error) {
	var p pkg
	err := s.db.GetContext(ctx, &p,
		`SELECT `+packageColumns+` FROM packages p WHERE p.id = ? AND p.is_active = 1`, id)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, nil
	}
	return &p, err
}

type dish struct {
	ID           int    `json:"id"`
	Name         string `json:"name"`
	Description  string `json:"description"`
	IsVegetarian bool   `json:"isVegetarian"`
	IsSpicy      bool   `json:"isSpicy"`
}

type menuCategory struct {
	ID     int    `json:"id"`
	Name   string `json:"name"`
	Choose int    `json:"choose"`
	Dishes []dish `json:"dishes"`
}

// getPackageMenu returns the package's categories in order, each with its
// "choose N" rule and active dishes (same query as getPackageMenu() in PHP).
func (s *store) getPackageMenu(ctx context.Context, packageID int) ([]menuCategory, error) {
	var rows []struct {
		CategoryID   int    `db:"category_id"`
		CategoryName string `db:"category_name"`
		Choose       int    `db:"choose_count"`
		DishID       int    `db:"dish_id"`
		DishName     string `db:"dish_name"`
		Description  string `db:"description"`
		IsVegetarian bool   `db:"is_vegetarian"`
		IsSpicy      bool   `db:"is_spicy"`
	}
	err := s.db.SelectContext(ctx, &rows,
		`SELECT c.id AS category_id, c.name AS category_name, r.choose_count,
		        d.id AS dish_id, d.name AS dish_name, d.description, d.is_vegetarian, d.is_spicy
		 FROM package_rules r
		 JOIN categories c      ON c.id = r.category_id
		 JOIN package_dishes pd ON pd.package_id = r.package_id
		 JOIN dishes d          ON d.id = pd.dish_id AND d.category_id = c.id
		 WHERE r.package_id = ? AND d.is_active = 1
		 ORDER BY c.sort_order, d.name`, packageID)
	if err != nil {
		return nil, err
	}

	menu := []menuCategory{}
	for _, row := range rows {
		if len(menu) == 0 || menu[len(menu)-1].ID != row.CategoryID {
			menu = append(menu, menuCategory{ID: row.CategoryID, Name: row.CategoryName, Choose: row.Choose})
		}
		cat := &menu[len(menu)-1]
		cat.Dishes = append(cat.Dishes, dish{
			ID: row.DishID, Name: row.DishName, Description: row.Description,
			IsVegetarian: row.IsVegetarian, IsSpicy: row.IsSpicy,
		})
	}
	return menu, nil
}

// ---------------------------------------------------------------------
// Orders
// ---------------------------------------------------------------------

// pricedLine is one validated package of a new order, priced from the database.
type pricedLine struct {
	Package *pkg
	Guests  int
	LineCts int64
	Dishes  []chosenDish
}

type chosenDish struct {
	ID       int
	Name     string
	Category string
}

type newOrder struct {
	Customer    customerInput
	Lines       []pricedLine
	SubtotalCts int64
	DeliveryCts int64
	TotalCts    int64
}

func cents(amount float64) int64 { return int64(math.Round(amount * 100)) }

func decimal(cts int64) string { return fmt.Sprintf("%d.%02d", cts/100, cts%100) }

// refChars leaves out 0/O and 1/I so references are easy to read out.
const refChars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"

func randomRef() string {
	b := []byte("MC-")
	for range 6 {
		n, _ := rand.Int(rand.Reader, big.NewInt(int64(len(refChars))))
		b = append(b, refChars[n.Int64()])
	}
	return string(b)
}

// createOrder writes the order exactly as checkout.php does, in one transaction:
// orders, order_items, order_item_dishes and the first order_status_history row.
func (s *store) createOrder(ctx context.Context, o newOrder) (string, error) {
	tx, err := s.db.BeginTxx(ctx, nil)
	if err != nil {
		return "", err
	}
	defer tx.Rollback()

	ref := ""
	for ref == "" {
		candidate := randomRef()
		var taken int
		if err := tx.GetContext(ctx, &taken, `SELECT COUNT(*) FROM orders WHERE order_ref = ?`, candidate); err != nil {
			return "", err
		}
		if taken == 0 {
			ref = candidate
		}
	}

	c := o.Customer
	res, err := tx.ExecContext(ctx,
		`INSERT INTO orders (order_ref, customer_name, email, phone, address, postal_code,
		                     event_date, time_slot, notes, subtotal, delivery_fee, total)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		ref, c.Name, c.Email, c.Phone, c.Address, c.PostalCode, c.EventDate, c.TimeSlot, c.Notes,
		decimal(o.SubtotalCts), decimal(o.DeliveryCts), decimal(o.TotalCts))
	if err != nil {
		return "", err
	}
	orderID, _ := res.LastInsertId()

	for _, line := range o.Lines {
		res, err := tx.ExecContext(ctx,
			`INSERT INTO order_items (order_id, package_id, package_name, price_per_pax, guests, line_total)
			 VALUES (?, ?, ?, ?, ?, ?)`,
			orderID, line.Package.ID, line.Package.Name, decimal(cents(line.Package.PricePerPax)),
			line.Guests, decimal(line.LineCts))
		if err != nil {
			return "", err
		}
		itemID, _ := res.LastInsertId()
		for _, d := range line.Dishes {
			if _, err := tx.ExecContext(ctx,
				`INSERT INTO order_item_dishes (order_item_id, dish_id, dish_name, category_name) VALUES (?, ?, ?, ?)`,
				itemID, d.ID, d.Name, d.Category); err != nil {
				return "", err
			}
		}
	}

	if _, err := tx.ExecContext(ctx,
		`INSERT INTO order_status_history (order_id, status, note) VALUES (?, 'pending_payment', 'Order placed')`,
		orderID); err != nil {
		return "", err
	}
	return ref, tx.Commit()
}

type orderRow struct {
	ID           int64   `db:"id"`
	Ref          string  `db:"order_ref"`
	CustomerName string  `db:"customer_name"`
	Email        string  `db:"email"`
	Phone        string  `db:"phone"`
	Address      string  `db:"address"`
	PostalCode   string  `db:"postal_code"`
	EventDate    string  `db:"event_date"`
	TimeSlot     string  `db:"time_slot"`
	Notes        string  `db:"notes"`
	Subtotal     float64 `db:"subtotal"`
	DeliveryFee  float64 `db:"delivery_fee"`
	Total        float64 `db:"total"`
	Status       string  `db:"status"`
	CreatedAt    string  `db:"created_at"`
}

// getOrderByRef returns (nil, nil) when no order has that reference.
func (s *store) getOrderByRef(ctx context.Context, ref string) (*orderRow, error) {
	var o orderRow
	err := s.db.GetContext(ctx, &o,
		`SELECT id, order_ref, customer_name, email, phone, address, postal_code, event_date, time_slot,
		        notes, subtotal, delivery_fee, total, status, created_at
		 FROM orders WHERE order_ref = ?`, ref)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, nil
	}
	return &o, err
}

type orderItem struct {
	ID          int64          `db:"id" json:"-"`
	PackageID   sql.NullInt64  `db:"package_id" json:"-"`
	PackageName string         `db:"package_name" json:"packageName"`
	PricePerPax float64        `db:"price_per_pax" json:"pricePerPax"`
	Guests      int            `db:"guests" json:"guests"`
	LineTotal   float64        `db:"line_total" json:"lineTotal"`
	ImageDB     sql.NullString `db:"image" json:"-"`
	Image       string         `json:"image"`
	Dishes      []orderDish    `json:"dishes"`
}

type orderDish struct {
	Name     string `db:"dish_name" json:"name"`
	Category string `db:"category_name" json:"category"`
}

// getOrderItems returns each item with its chosen dishes and its package photo
// (or a general photo if the package was deleted since, like getOrderImage()).
func (s *store) getOrderItems(ctx context.Context, orderID int64) ([]orderItem, error) {
	items := []orderItem{}
	err := s.db.SelectContext(ctx, &items,
		`SELECT i.id, i.package_id, i.package_name, i.price_per_pax, i.guests, i.line_total, p.image
		 FROM order_items i LEFT JOIN packages p ON p.id = i.package_id
		 WHERE i.order_id = ? ORDER BY i.id`, orderID)
	if err != nil {
		return nil, err
	}
	for i := range items {
		items[i].Image = "images/hero-spread.jpg"
		if items[i].ImageDB.Valid {
			items[i].Image = items[i].ImageDB.String
		}
		items[i].Dishes = []orderDish{}
		if err := s.db.SelectContext(ctx, &items[i].Dishes,
			`SELECT dish_name, category_name FROM order_item_dishes WHERE order_item_id = ?`, items[i].ID); err != nil {
			return nil, err
		}
	}
	return items, nil
}

type historyEvent struct {
	Status    string `db:"status" json:"status"`
	Label     string `json:"label"`
	Note      string `db:"note" json:"note"`
	ChangedAt string `db:"changed_at" json:"changedAt"`
}

func (s *store) getOrderHistory(ctx context.Context, orderID int64) ([]historyEvent, error) {
	events := []historyEvent{}
	err := s.db.SelectContext(ctx, &events,
		`SELECT status, note, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at, id`, orderID)
	for i := range events {
		events[i].Label = statusLabel(events[i].Status)
	}
	return events, err
}

// errNotPayable means the order was paid, cancelled or changed by someone else first.
var errNotPayable = errors.New("order is not awaiting payment")

// recordPayment applies the payment gateway's answer. The status check is part of
// the UPDATE, so two answers for the same order can never both succeed.
func (s *store) recordPayment(ctx context.Context, orderID int64, success bool) error {
	status, note := "payment_failed", "Payment was declined"
	paidAt := ""
	if success {
		status, note = "confirmed", "Payment received"
		paidAt = ", paid_at = COALESCE(paid_at, NOW())"
	}

	tx, err := s.db.BeginTxx(ctx, nil)
	if err != nil {
		return err
	}
	defer tx.Rollback()

	res, err := tx.ExecContext(ctx,
		`UPDATE orders SET status = ?`+paidAt+`
		 WHERE id = ? AND status IN ('pending_payment', 'payment_failed')`, status, orderID)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return errNotPayable
	}
	if _, err := tx.ExecContext(ctx,
		`INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)`, orderID, status, note); err != nil {
		return err
	}
	return tx.Commit()
}

// orderSignature changes whenever staff update the order, which is what the
// live tracking stream watches for.
func (s *store) orderSignature(ctx context.Context, orderID int64) (string, error) {
	var sig string
	err := s.db.GetContext(ctx, &sig,
		`SELECT CONCAT(o.status, ':', COUNT(h.id), ':', COALESCE(MAX(h.id), 0))
		 FROM orders o LEFT JOIN order_status_history h ON h.order_id = o.id
		 WHERE o.id = ? GROUP BY o.id, o.status`, orderID)
	return sig, err
}

func (s *store) logEmail(ctx context.Context, orderID int64, to, subject, body string, sent bool) error {
	_, err := s.db.ExecContext(ctx,
		`INSERT INTO email_log (order_id, to_email, subject, body, sent_ok) VALUES (?, ?, ?, ?, ?)`,
		orderID, to, subject, body, sent)
	return err
}
