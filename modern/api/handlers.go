package main

import (
	"context"
	"crypto/subtle"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"strconv"
	"strings"
)

type app struct {
	store *store
	cfg   config
	log   *slog.Logger
}

// ---------------------------------------------------------------------
// JSON helpers
// ---------------------------------------------------------------------

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.Header().Set("Cache-Control", "no-store")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func writeError(w http.ResponseWriter, status int, message string) {
	writeJSON(w, status, map[string]string{"error": message})
}

func (a *app) serverError(w http.ResponseWriter, r *http.Request, err error) {
	a.log.Error("request failed", "method", r.Method, "path", r.URL.Path, "err", err)
	writeError(w, http.StatusInternalServerError,
		"Something went wrong on our side. Please try again in a moment.")
}

func readJSON(w http.ResponseWriter, r *http.Request, dst any) bool {
	r.Body = http.MaxBytesReader(w, r.Body, 64<<10)
	if err := json.NewDecoder(r.Body).Decode(dst); err != nil {
		writeError(w, http.StatusBadRequest, "The request could not be read.")
		return false
	}
	return true
}

// ---------------------------------------------------------------------
// Menu
// ---------------------------------------------------------------------

// GET /api/rules: business rules the React app needs for its forms.
func (a *app) getRules(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{
		"siteName":        siteName,
		"contactPhone":    contactPhone,
		"contactEmail":    contactEmail,
		"leadDays":        leadDays,
		"maxGuests":       maxGuests,
		"deliveryFee":     float64(deliveryFeeCts) / 100,
		"freeDeliveryMin": float64(freeDeliveryCts) / 100,
		"timeSlots":       timeSlots,
		"earliestDate":    earliestDeliveryDate().Format("2006-01-02"),
		"latestDate":      latestDeliveryDate().Format("2006-01-02"),
		"statusLabels":    statusLabels,
	})
}

// GET /api/packages
func (a *app) listPackages(w http.ResponseWriter, r *http.Request) {
	pkgs, err := a.store.listPackages(r.Context())
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, pkgs)
}

// GET /api/packages/{id}: one package with its menu.
func (a *app) getPackage(w http.ResponseWriter, r *http.Request) {
	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil || id < 1 {
		writeError(w, http.StatusNotFound, "That package does not exist.")
		return
	}
	p, err := a.store.getPackage(r.Context(), id)
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	if p == nil {
		writeError(w, http.StatusNotFound, "That package is no longer on the menu.")
		return
	}
	menu, err := a.store.getPackageMenu(r.Context(), id)
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"package": p, "menu": menu})
}

// ---------------------------------------------------------------------
// Orders
// ---------------------------------------------------------------------

type orderView struct {
	Ref          string         `json:"ref"`
	CustomerName string         `json:"customerName"`
	Email        string         `json:"email"`
	Phone        string         `json:"phone"`
	Address      string         `json:"address"`
	PostalCode   string         `json:"postalCode"`
	EventDate    string         `json:"eventDate"`
	TimeSlot     string         `json:"timeSlot"`
	Notes        string         `json:"notes"`
	Subtotal     float64        `json:"subtotal"`
	DeliveryFee  float64        `json:"deliveryFee"`
	Total        float64        `json:"total"`
	Status       string         `json:"status"`
	StatusLabel  string         `json:"statusLabel"`
	Payable      bool           `json:"payable"`
	CreatedAt    string         `json:"createdAt"`
	Items        []orderItem    `json:"items"`
	History      []historyEvent `json:"history"`
}

func (a *app) buildOrderView(ctx context.Context, o *orderRow) (*orderView, error) {
	items, err := a.store.getOrderItems(ctx, o.ID)
	if err != nil {
		return nil, err
	}
	history, err := a.store.getOrderHistory(ctx, o.ID)
	if err != nil {
		return nil, err
	}
	return &orderView{
		Ref: o.Ref, CustomerName: o.CustomerName, Email: o.Email, Phone: o.Phone,
		Address: o.Address, PostalCode: o.PostalCode, EventDate: o.EventDate, TimeSlot: o.TimeSlot,
		Notes: o.Notes, Subtotal: o.Subtotal, DeliveryFee: o.DeliveryFee, Total: o.Total,
		Status: o.Status, StatusLabel: statusLabel(o.Status),
		Payable:   o.Status == "pending_payment" || o.Status == "payment_failed",
		CreatedAt: o.CreatedAt, Items: items, History: history,
	}, nil
}

// lookupOrder finds an order by reference and checks the email matches, like
// track.php. It returns (nil, nil) for an unknown order or a wrong email.
func (a *app) lookupOrder(ctx context.Context, ref, email string) (*orderRow, error) {
	ref = strings.ToUpper(strings.TrimSpace(ref))
	email = strings.ToLower(strings.TrimSpace(email))
	if !validOrderRef(ref) || !validEmail(email) {
		return nil, nil
	}
	o, err := a.store.getOrderByRef(ctx, ref)
	if err != nil || o == nil {
		return nil, err
	}
	if subtle.ConstantTimeCompare([]byte(strings.ToLower(o.Email)), []byte(email)) != 1 {
		return nil, nil
	}
	return o, nil
}

const orderNotFound = "We could not find an order with that number and email. Check both and try again."

type orderItemInput struct {
	PackageID int   `json:"packageId"`
	Guests    int   `json:"guests"`
	DishIDs   []int `json:"dishIds"`
}

// POST /api/orders: validate the cart and customer details, price everything from
// the database (never from the browser) and save the order awaiting payment.
func (a *app) createOrder(w http.ResponseWriter, r *http.Request) {
	var in struct {
		Customer customerInput    `json:"customer"`
		Items    []orderItemInput `json:"items"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	ctx := r.Context()

	in.Customer.normalise()
	fieldErrs := in.Customer.validate()
	itemErrs := map[int][]string{}
	var cartErr string

	switch {
	case len(in.Items) == 0:
		cartErr = "Your cart is empty."
	case len(in.Items) > maxCartItems:
		cartErr = "An order can have at most " + strconv.Itoa(maxCartItems) + " packages."
	}

	order := newOrder{Customer: in.Customer}
	if cartErr == "" {
		for i, item := range in.Items {
			p, err := a.store.getPackage(ctx, item.PackageID)
			if err != nil {
				a.serverError(w, r, err)
				return
			}
			if p == nil {
				itemErrs[i] = []string{"This package is no longer on the menu. Please remove it."}
				continue
			}
			var errs []string
			if item.Guests < p.MinPax || item.Guests > maxGuests {
				errs = append(errs, "Guests must be between "+strconv.Itoa(p.MinPax)+" and "+
					strconv.Itoa(maxGuests)+" for this package.")
			}
			menu, err := a.store.getPackageMenu(ctx, p.ID)
			if err != nil {
				a.serverError(w, r, err)
				return
			}
			errs = append(errs, validateDishSelection(menu, item.DishIDs)...)
			if len(errs) > 0 {
				itemErrs[i] = errs
				continue
			}

			chosen := map[int]bool{}
			for _, id := range item.DishIDs {
				chosen[id] = true
			}
			line := pricedLine{Package: p, Guests: item.Guests, LineCts: cents(p.PricePerPax) * int64(item.Guests)}
			for _, cat := range menu {
				for _, d := range cat.Dishes {
					if chosen[d.ID] {
						line.Dishes = append(line.Dishes, chosenDish{ID: d.ID, Name: d.Name, Category: cat.Name})
					}
				}
			}
			order.Lines = append(order.Lines, line)
			order.SubtotalCts += line.LineCts
		}
	}

	if len(fieldErrs) > 0 || len(itemErrs) > 0 || cartErr != "" {
		message := "Please correct the highlighted details."
		if cartErr != "" {
			message = cartErr
		} else if len(itemErrs) > 0 {
			message = "Some items in your cart need attention."
		}
		writeJSON(w, http.StatusUnprocessableEntity, map[string]any{
			"error": message, "fields": fieldErrs, "items": itemErrs,
		})
		return
	}

	order.DeliveryCts = deliveryFee(order.SubtotalCts)
	order.TotalCts = order.SubtotalCts + order.DeliveryCts
	ref, err := a.store.createOrder(ctx, order)
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	a.log.Info("order placed", "ref", ref, "total", decimal(order.TotalCts))
	writeJSON(w, http.StatusCreated, map[string]any{"ref": ref, "total": float64(order.TotalCts) / 100})
}

// GET /api/orders/{ref}?email=
func (a *app) getOrder(w http.ResponseWriter, r *http.Request) {
	o, err := a.lookupOrder(r.Context(), r.PathValue("ref"), r.URL.Query().Get("email"))
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	if o == nil {
		writeError(w, http.StatusNotFound, orderNotFound)
		return
	}
	view, err := a.buildOrderView(r.Context(), o)
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, view)
}

// POST /api/orders/{ref}/payment with {"email", "outcome": "success" | "failed"}.
// Stands in for the third-party gateway's answer, like payment.php.
func (a *app) payOrder(w http.ResponseWriter, r *http.Request) {
	var in struct {
		Email   string `json:"email"`
		Outcome string `json:"outcome"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	if in.Outcome != "success" && in.Outcome != "failed" {
		writeError(w, http.StatusBadRequest, "Unknown payment outcome.")
		return
	}
	ctx := r.Context()
	o, err := a.lookupOrder(ctx, r.PathValue("ref"), in.Email)
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	if o == nil {
		writeError(w, http.StatusNotFound, orderNotFound)
		return
	}

	err = a.store.recordPayment(ctx, o.ID, in.Outcome == "success")
	if errors.Is(err, errNotPayable) {
		writeError(w, http.StatusConflict, "This order has already been paid or cancelled.")
		return
	}
	if err != nil {
		a.serverError(w, r, err)
		return
	}

	o, err = a.store.getOrderByRef(ctx, o.Ref)
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	view, err := a.buildOrderView(ctx, o)
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	var emailSent bool
	if in.Outcome == "success" {
		emailSent = a.sendOrderConfirmation(ctx, o, view.Items)
	} else {
		emailSent = a.sendPaymentFailure(ctx, o)
	}
	writeJSON(w, http.StatusOK, map[string]any{"order": view, "emailSent": emailSent})
}
