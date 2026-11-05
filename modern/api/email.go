package main

import (
	"context"
	"fmt"
	"mime"
	"net"
	"net/mail"
	"net/smtp"
	"net/url"
	"strconv"
	"strings"
	"time"
)

// Emails go over SMTP to Mailpit (localhost:1025 by default), which catches them
// in a local inbox and sends nothing out, as the project brief requires. Every
// email is also copied to email_log, so it shows in the PHP staff area's email log
// even when Mailpit is not running.

func (a *app) sendEmail(ctx context.Context, orderID int64, to, subject, body string) bool {
	err := smtpSend(a.cfg.SMTPAddr, to, subject, body)
	if err != nil {
		a.log.Warn("email not sent (is Mailpit running?)", "to", to, "err", err)
	}
	if logErr := a.store.logEmail(ctx, orderID, to, subject, body, err == nil); logErr != nil {
		a.log.Error("email_log insert failed", "err", logErr)
	}
	return err == nil
}

func smtpSend(addr, to, subject, body string) error {
	conn, err := net.DialTimeout("tcp", addr, 3*time.Second)
	if err != nil {
		return err
	}
	_ = conn.SetDeadline(time.Now().Add(10 * time.Second))
	host, _, _ := net.SplitHostPort(addr)
	client, err := smtp.NewClient(conn, host)
	if err != nil {
		conn.Close()
		return err
	}
	defer client.Close()

	if err := client.Mail(contactEmail); err != nil {
		return err
	}
	if err := client.Rcpt(to); err != nil {
		return err
	}
	w, err := client.Data()
	if err != nil {
		return err
	}
	from := mail.Address{Name: siteName, Address: contactEmail}
	headers := "From: " + from.String() + "\n" +
		"To: <" + to + ">\n" +
		"Subject: " + mime.QEncoding.Encode("utf-8", subject) + "\n" +
		"Date: " + time.Now().Format(time.RFC1123Z) + "\n" +
		"MIME-Version: 1.0\n" +
		"Content-Type: text/plain; charset=UTF-8\n" +
		"Content-Transfer-Encoding: 8bit\n\n"
	// The DATA writer converts \n to \r\n and escapes leading dots.
	if _, err := w.Write([]byte(headers + body)); err != nil {
		return err
	}
	if err := w.Close(); err != nil {
		return err
	}
	return client.Quit()
}

// money formats an amount like PHP's money(): $1,234.50
func money(amount float64) string {
	cts := cents(amount)
	whole := strconv.FormatInt(cts/100, 10)
	var b strings.Builder
	for i, digit := range whole {
		if i > 0 && (len(whole)-i)%3 == 0 {
			b.WriteByte(',')
		}
		b.WriteRune(digit)
	}
	return fmt.Sprintf("$%s.%02d", b.String(), cts%100)
}

func longDate(value string) string {
	d, err := time.Parse("2006-01-02", value)
	if err != nil {
		return value
	}
	return d.Format("Monday, 2 January 2006")
}

// orderSummaryText matches orderSummaryText() in functions.php.
func orderSummaryText(o *orderRow, items []orderItem) string {
	var b strings.Builder
	fmt.Fprintf(&b, "Order: %s\nDelivery: %s, %s\nAddress: %s, Singapore %s\n\n",
		o.Ref, longDate(o.EventDate), o.TimeSlot, o.Address, o.PostalCode)
	for _, item := range items {
		fmt.Fprintf(&b, "%s x %d guests: %s\n", item.PackageName, item.Guests, money(item.LineTotal))
		for _, d := range item.Dishes {
			fmt.Fprintf(&b, "  - %s\n", d.Name)
		}
	}
	delivery := "Free"
	if o.DeliveryFee > 0 {
		delivery = money(o.DeliveryFee)
	}
	fmt.Fprintf(&b, "\nSubtotal: %s\nDelivery: %s\nTotal: %s\n", money(o.Subtotal), delivery, money(o.Total))
	return b.String()
}

func (a *app) trackURL(o *orderRow) string {
	q := url.Values{"ref": {o.Ref}, "email": {o.Email}}
	return strings.TrimRight(a.cfg.AppURL, "/") + "/track?" + q.Encode()
}

func (a *app) sendOrderConfirmation(ctx context.Context, o *orderRow, items []orderItem) bool {
	body := "Hi " + o.CustomerName + ",\n\n" +
		"Thank you for your order. Payment was successful and your buffet is confirmed.\n\n" +
		orderSummaryText(o, items) +
		"\nTrack your order at any time:\n" + a.trackURL(o) + "\n\n" +
		siteName + "\n" + contactPhone + "\n"
	return a.sendEmail(ctx, o.ID, o.Email, "Order confirmed: "+o.Ref, body)
}

// Same wording as sendPaymentFailure() in base/includes/functions.php.
func (a *app) sendPaymentFailure(ctx context.Context, o *orderRow) bool {
	body := "Hi " + o.CustomerName + ",\n\n" +
		"Payment for your order " + o.Ref + " was declined. You have not been charged, and your buffet is not confirmed yet.\n\n" +
		"Your order has been saved. Open the tracking link below and choose Complete payment to try again:\n" +
		a.trackURL(o) + "\n\n" +
		siteName + "\n" + contactPhone + "\n"
	return a.sendEmail(ctx, o.ID, o.Email, "Payment failed: "+o.Ref, body)
}
