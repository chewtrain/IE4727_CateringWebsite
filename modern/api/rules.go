package main

import "time"

// Business rules. These mirror base/includes/config.php so both versions of the
// site accept and price orders the same way. Change them in both places.
const (
	siteName        = "Makan & Co."
	contactPhone    = "+65 6123 4567"
	contactEmail    = "makanco@localhost"
	leadDays        = 3     // orders need this many days' notice
	maxDaysAhead    = 180   // and can be placed up to ~6 months ahead
	maxGuests       = 500   // per package
	deliveryFeeCts  = 3000  // $30.00 flat delivery and set-up fee
	freeDeliveryCts = 50000 // orders of $500.00 or more ship free
	maxCartItems    = 20    // packages per order
)

// Delivery time slots offered at checkout (same strings as the PHP site).
var timeSlots = []string{
	"11:00 - 12:00",
	"12:00 - 13:00",
	"15:00 - 16:00",
	"18:00 - 19:00",
}

// Order statuses with customer-facing labels, in the order they happen.
var statusLabels = map[string]string{
	"pending_payment":  "Awaiting payment",
	"payment_failed":   "Payment failed",
	"confirmed":        "Confirmed",
	"preparing":        "Preparing",
	"out_for_delivery": "Out for delivery",
	"delivered":        "Delivered",
	"cancelled":        "Cancelled",
}

func statusLabel(status string) string {
	if label, ok := statusLabels[status]; ok {
		return label
	}
	return status
}

// Singapore time, embedded via time/tzdata so this also works on Windows.
var sgt = mustLoadLocation("Asia/Singapore")

func mustLoadLocation(name string) *time.Location {
	loc, err := time.LoadLocation(name)
	if err != nil {
		panic(err)
	}
	return loc
}

// today is midnight at the start of the current day in Singapore.
func today() time.Time {
	now := time.Now().In(sgt)
	return time.Date(now.Year(), now.Month(), now.Day(), 0, 0, 0, 0, sgt)
}

func earliestDeliveryDate() time.Time { return today().AddDate(0, 0, leadDays) }
func latestDeliveryDate() time.Time   { return today().AddDate(0, 0, maxDaysAhead) }

// deliveryFee in cents for a subtotal in cents (free when empty or large).
func deliveryFee(subtotalCts int64) int64 {
	if subtotalCts == 0 || subtotalCts >= freeDeliveryCts {
		return 0
	}
	return deliveryFeeCts
}
