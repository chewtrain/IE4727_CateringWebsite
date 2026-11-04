package main

import (
	"net/mail"
	"regexp"
	"slices"
	"strconv"
	"strings"
	"time"
)

// Server-side validation. The same rules as base/includes/functions.php, and the
// React form checks them too, but the browser can always be bypassed.

var (
	nameRe     = regexp.MustCompile(`^[A-Za-z][A-Za-z .'/-]{1,59}$`)
	emailRe    = regexp.MustCompile(`^[^\s@]+@[^\s@]+\.[^\s@]+$`)
	phoneRe    = regexp.MustCompile(`^[689]\d{7}$`)
	postalRe   = regexp.MustCompile(`^\d{6}$`)
	orderRefRe = regexp.MustCompile(`^MC-[A-Z0-9]{6}$`)
)

func validName(name string) bool { return nameRe.MatchString(name) }

func validEmail(email string) bool {
	if len(email) > 120 || !emailRe.MatchString(email) {
		return false
	}
	addr, err := mail.ParseAddress(email)
	return err == nil && addr.Address == email
}

// Singapore numbers: 8 digits starting with 6, 8 or 9.
func validPhone(phone string) bool { return phoneRe.MatchString(phone) }

// Singapore postal codes: 6 digits; the first two (the sector) run 01-82, except 74.
func validPostalCode(code string) bool {
	if !postalRe.MatchString(code) {
		return false
	}
	sector, _ := strconv.Atoi(code[:2])
	return sector >= 1 && sector <= 82 && sector != 74
}

func validOrderRef(ref string) bool { return orderRefRe.MatchString(ref) }

// Event date must be a real date between the lead time and the booking horizon.
func validEventDate(value string) bool {
	date, err := time.ParseInLocation("2006-01-02", value, sgt)
	if err != nil {
		return false
	}
	return !date.Before(earliestDeliveryDate()) && !date.After(latestDeliveryDate())
}

func validTimeSlot(slot string) bool { return slices.Contains(timeSlots, slot) }

// customerInput is the contact and delivery part of a new order.
type customerInput struct {
	Name         string `json:"name"`
	Email        string `json:"email"`
	EmailConfirm string `json:"emailConfirm"`
	Phone        string `json:"phone"`
	Address      string `json:"address"`
	PostalCode   string `json:"postalCode"`
	EventDate    string `json:"eventDate"`
	TimeSlot     string `json:"timeSlot"`
	Notes        string `json:"notes"`
	Terms        bool   `json:"terms"`
}

// normalise trims every field and applies the same clean-up as checkout.php.
func (c *customerInput) normalise() {
	c.Name = strings.TrimSpace(c.Name)
	c.Email = strings.ToLower(strings.TrimSpace(c.Email))
	c.EmailConfirm = strings.ToLower(strings.TrimSpace(c.EmailConfirm))
	c.Phone = strings.ReplaceAll(strings.TrimSpace(c.Phone), " ", "")
	c.Address = strings.TrimSpace(c.Address)
	c.PostalCode = strings.TrimSpace(c.PostalCode)
	c.EventDate = strings.TrimSpace(c.EventDate)
	c.TimeSlot = strings.TrimSpace(c.TimeSlot)
	c.Notes = strings.TrimSpace(c.Notes)
}

// validate returns field name -> message for every invalid field.
func (c *customerInput) validate() map[string]string {
	errs := map[string]string{}
	if !validName(c.Name) {
		errs["name"] = "Enter your full name using letters only."
	}
	if !validEmail(c.Email) {
		errs["email"] = "Enter a valid email address."
	} else if c.Email != c.EmailConfirm {
		errs["emailConfirm"] = "The two email addresses do not match."
	}
	if !validPhone(c.Phone) {
		errs["phone"] = "Enter an 8-digit Singapore number starting with 6, 8 or 9."
	}
	if len(c.Address) < 5 || len(c.Address) > 200 {
		errs["address"] = "Enter the delivery address (block, street and unit)."
	}
	if !validPostalCode(c.PostalCode) {
		errs["postalCode"] = "Enter a valid 6-digit Singapore postal code."
	}
	if !validEventDate(c.EventDate) {
		errs["eventDate"] = "Choose a date between " + earliestDeliveryDate().Format("2 Jan 2006") +
			" and " + latestDeliveryDate().Format("2 Jan 2006") + "."
	}
	if !validTimeSlot(c.TimeSlot) {
		errs["timeSlot"] = "Choose a delivery time slot."
	}
	if len(c.Notes) > 500 {
		errs["notes"] = "Keep notes under 500 characters."
	}
	if !c.Terms {
		errs["terms"] = "Please accept the delivery terms."
	}
	return errs
}

// validateDishSelection checks chosen dish IDs against a package menu, exactly
// like validateDishSelection() in functions.php.
func validateDishSelection(menu []menuCategory, dishIDs []int) []string {
	var errs []string
	remaining := map[int]bool{}
	for _, id := range dishIDs {
		remaining[id] = true
	}
	for _, cat := range menu {
		picked := 0
		for _, dish := range cat.Dishes {
			if remaining[dish.ID] {
				picked++
				delete(remaining, dish.ID)
			}
		}
		if picked != cat.Choose {
			errs = append(errs, "Choose exactly "+strconv.Itoa(cat.Choose)+" from "+cat.Name+
				" (you chose "+strconv.Itoa(picked)+").")
		}
	}
	if len(remaining) > 0 {
		errs = append(errs, "Some of the dishes you chose are not part of this package.")
	}
	return errs
}
