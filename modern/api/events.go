package main

import (
	"encoding/json"
	"fmt"
	"net/http"
	"time"
)

// GET /api/orders/{ref}/events?email=
//
// Live order tracking with Server-Sent Events. The stream sends the order once,
// then checks the database every two seconds and sends it again whenever the
// status or history changes, for example when staff update the order in the
// PHP staff area. The browser's EventSource reconnects by itself if it drops.
func (a *app) orderEvents(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	o, err := a.lookupOrder(ctx, r.PathValue("ref"), r.URL.Query().Get("email"))
	if err != nil {
		a.serverError(w, r, err)
		return
	}
	if o == nil {
		writeError(w, http.StatusNotFound, orderNotFound)
		return
	}

	rc := http.NewResponseController(w)
	_ = rc.SetWriteDeadline(time.Time{}) // this response stays open
	h := w.Header()
	h.Set("Content-Type", "text/event-stream")
	h.Set("Cache-Control", "no-cache")
	h.Set("Connection", "keep-alive")
	h.Set("X-Accel-Buffering", "no")
	w.WriteHeader(http.StatusOK)

	sendOrder := func() bool {
		fresh, err := a.store.getOrderByRef(ctx, o.Ref)
		if err != nil || fresh == nil {
			return false
		}
		view, err := a.buildOrderView(ctx, fresh)
		if err != nil {
			return false
		}
		data, _ := json.Marshal(view)
		if _, err := fmt.Fprintf(w, "event: order\ndata: %s\n\n", data); err != nil {
			return false
		}
		return rc.Flush() == nil
	}

	fmt.Fprint(w, "retry: 3000\n\n")
	last, _ := a.store.orderSignature(ctx, o.ID)
	if !sendOrder() {
		return
	}

	poll := time.NewTicker(2 * time.Second)
	defer poll.Stop()
	keepAlive := time.NewTicker(20 * time.Second)
	defer keepAlive.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-keepAlive.C:
			if _, err := fmt.Fprint(w, ": keep-alive\n\n"); err != nil || rc.Flush() != nil {
				return
			}
		case <-poll.C:
			sig, err := a.store.orderSignature(ctx, o.ID)
			if err != nil || sig == last {
				continue
			}
			last = sig
			a.log.Info("order changed, pushing update", "ref", o.Ref, "signature", sig)
			if !sendOrder() {
				return
			}
		}
	}
}
