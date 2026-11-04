// Command api is the JSON API behind the React version of Makan & Co.
//
// It reads and writes the same makanco MariaDB/MySQL database as the PHP base
// version, so orders placed here appear in the PHP staff area, and status changes
// made there appear live on the React tracking page.
package main

import (
	"context"
	"errors"
	"log/slog"
	"net"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"strings"
	"syscall"
	"time"
	_ "time/tzdata" // Asia/Singapore works on Windows too

	"github.com/go-sql-driver/mysql"
	"github.com/jmoiron/sqlx"
)

type config struct {
	Port      string
	DBHost    string
	DBPort    string
	DBUser    string
	DBPass    string
	DBName    string
	ImagesDir string // the base version's images folder, shared rather than copied
	WebDist   string // built React app, served when present
	SMTPAddr  string
	AppURL    string // where the React app runs, for links in emails
}

func env(key, fallback string) string {
	if v, ok := os.LookupEnv(key); ok {
		return v
	}
	return fallback
}

// Defaults match XAMPP (root, no password) and assume the API runs from modern/api.
func loadConfig() config {
	return config{
		Port:      env("PORT", "8080"),
		DBHost:    env("DB_HOST", "127.0.0.1"),
		DBPort:    env("DB_PORT", "3306"),
		DBUser:    env("DB_USER", "root"),
		DBPass:    env("DB_PASS", ""),
		DBName:    env("DB_NAME", "makanco"),
		ImagesDir: env("IMAGES_DIR", filepath.Join("..", "..", "base", "images")),
		WebDist:   env("WEB_DIST", filepath.Join("..", "web", "dist")),
		SMTPAddr:  env("SMTP_ADDR", "localhost:1025"),
		AppURL:    env("APP_URL", "http://localhost:5173"),
	}
}

func openDB(cfg config) (*sqlx.DB, error) {
	mc := mysql.NewConfig()
	mc.User = cfg.DBUser
	mc.Passwd = cfg.DBPass
	mc.Net = "tcp"
	mc.Addr = net.JoinHostPort(cfg.DBHost, cfg.DBPort)
	mc.DBName = cfg.DBName
	mc.Collation = "utf8mb4_unicode_ci"
	db, err := sqlx.Open("mysql", mc.FormatDSN())
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(10)
	db.SetConnMaxLifetime(5 * time.Minute)
	return db, nil
}

func main() {
	log := slog.New(slog.NewTextHandler(os.Stderr, nil))
	cfg := loadConfig()

	db, err := openDB(cfg)
	if err != nil {
		log.Error("bad database settings", "err", err)
		os.Exit(1)
	}
	defer db.Close()
	pingCtx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	if err := db.PingContext(pingCtx); err != nil {
		log.Warn("database not reachable yet; start MySQL in XAMPP and import db/schema.sql and db/seed.sql",
			"addr", net.JoinHostPort(cfg.DBHost, cfg.DBPort), "err", err)
	}
	cancel()

	a := &app{store: &store{db: db}, cfg: cfg, log: log}

	mux := http.NewServeMux()
	mux.HandleFunc("GET /api/rules", a.getRules)
	mux.HandleFunc("GET /api/packages", a.listPackages)
	mux.HandleFunc("GET /api/packages/{id}", a.getPackage)
	mux.HandleFunc("POST /api/orders", a.createOrder)
	mux.HandleFunc("GET /api/orders/{ref}", a.getOrder)
	mux.HandleFunc("POST /api/orders/{ref}/payment", a.payOrder)
	mux.HandleFunc("GET /api/orders/{ref}/events", a.orderEvents)
	mux.HandleFunc("/api/", func(w http.ResponseWriter, r *http.Request) {
		writeError(w, http.StatusNotFound, "Unknown API route.")
	})
	mux.Handle("GET /images/", http.StripPrefix("/images/", noListing(http.FileServer(http.Dir(cfg.ImagesDir)))))
	if info, err := os.Stat(cfg.WebDist); err == nil && info.IsDir() {
		// No method in the pattern: "GET /" would clash with the "/api/" catch-all
		// above, so spaHandler checks the method itself.
		mux.Handle("/", spaHandler(cfg.WebDist))
		log.Info("serving the built React app", "dir", cfg.WebDist)
	}

	srv := &http.Server{
		Addr:              ":" + cfg.Port,
		Handler:           logRequests(log, mux),
		ReadHeaderTimeout: 5 * time.Second,
	}

	go func() {
		log.Info("API listening", "url", "http://localhost:"+cfg.Port)
		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Error("server stopped", "err", err)
			os.Exit(1)
		}
	}()

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)
	<-stop
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	_ = srv.Shutdown(ctx)
}

// noListing hides directory listings for the shared images folder.
func noListing(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "" || strings.HasSuffix(r.URL.Path, "/") {
			http.NotFound(w, r)
			return
		}
		w.Header().Set("Cache-Control", "public, max-age=86400")
		next.ServeHTTP(w, r)
	})
}

// spaHandler serves the built React app, falling back to index.html so
// client-side routes such as /track work on reload.
func spaHandler(dir string) http.Handler {
	files := http.FileServer(http.Dir(dir))
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet && r.Method != http.MethodHead {
			w.Header().Set("Allow", "GET, HEAD")
			http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
			return
		}
		path := filepath.Join(dir, filepath.FromSlash(filepath.Clean("/"+r.URL.Path)))
		if info, err := os.Stat(path); err != nil || info.IsDir() {
			http.ServeFile(w, r, filepath.Join(dir, "index.html"))
			return
		}
		files.ServeHTTP(w, r)
	})
}

// statusRecorder remembers the status code for the request log. Unwrap lets
// http.ResponseController reach the real writer, which live tracking needs.
type statusRecorder struct {
	http.ResponseWriter
	status int
}

func (s *statusRecorder) WriteHeader(code int) {
	s.status = code
	s.ResponseWriter.WriteHeader(code)
}

func (s *statusRecorder) Unwrap() http.ResponseWriter { return s.ResponseWriter }

func logRequests(log *slog.Logger, next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		rec := &statusRecorder{ResponseWriter: w, status: http.StatusOK}
		next.ServeHTTP(rec, r)
		if strings.HasPrefix(r.URL.Path, "/api/") {
			log.Info("request", "method", r.Method, "path", r.URL.Path, "status", rec.status,
				"ms", time.Since(start).Milliseconds())
		}
	})
}
