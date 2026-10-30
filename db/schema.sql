-- Makan & Co. catering database
-- Works on MySQL 8 and MariaDB 10.4+ (the version bundled with XAMPP).
-- Import order: schema.sql, then seed.sql.

DROP DATABASE IF EXISTS makanco;
CREATE DATABASE makanco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE makanco;

-- ---------------------------------------------------------------------
-- Menu
-- ---------------------------------------------------------------------

-- Buffet packages sold per guest.
CREATE TABLE packages (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(60)  NOT NULL,
    tagline       VARCHAR(120) NOT NULL,
    description   TEXT         NOT NULL,
    price_per_pax DECIMAL(6,2) NOT NULL,
    min_pax       SMALLINT UNSIGNED NOT NULL,
    image         VARCHAR(120) NOT NULL,
    image_alt     VARCHAR(160) NOT NULL,
    is_featured   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order    SMALLINT     NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Courses that dishes are grouped into, e.g. Mains, Desserts.
CREATE TABLE categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(40) NOT NULL UNIQUE,
    sort_order SMALLINT    NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE dishes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   INT UNSIGNED NOT NULL,
    name          VARCHAR(80)  NOT NULL,
    description   VARCHAR(200) NOT NULL DEFAULT '',
    is_vegetarian TINYINT(1)   NOT NULL DEFAULT 0,
    is_spicy      TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- How many dishes a customer picks from each category of a package.
CREATE TABLE package_rules (
    package_id   INT UNSIGNED NOT NULL,
    category_id  INT UNSIGNED NOT NULL,
    choose_count TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (package_id, category_id),
    FOREIGN KEY (package_id)  REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Which dishes are offered in which package (many-to-many).
CREATE TABLE package_dishes (
    package_id INT UNSIGNED NOT NULL,
    dish_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (package_id, dish_id),
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (dish_id)    REFERENCES dishes(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Orders (guest checkout: customer details are stored on the order)
-- ---------------------------------------------------------------------

CREATE TABLE orders (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_ref      CHAR(9)      NOT NULL UNIQUE,      -- e.g. MC-7K3QX2
    customer_name  VARCHAR(60)  NOT NULL,
    email          VARCHAR(120) NOT NULL,
    phone          CHAR(8)      NOT NULL,
    address        VARCHAR(200) NOT NULL,
    postal_code    CHAR(6)      NOT NULL,
    event_date     DATE         NOT NULL,
    time_slot      VARCHAR(20)  NOT NULL,
    notes          VARCHAR(500) NOT NULL DEFAULT '',
    subtotal       DECIMAL(9,2) NOT NULL,
    delivery_fee   DECIMAL(6,2) NOT NULL,
    total          DECIMAL(9,2) NOT NULL,
    status         ENUM('pending_payment', 'payment_failed', 'confirmed', 'preparing',
                        'out_for_delivery', 'delivered', 'cancelled')
                   NOT NULL DEFAULT 'pending_payment',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at        DATETIME     NULL,
    INDEX idx_orders_email (email),
    INDEX idx_orders_status (status),
    INDEX idx_orders_event_date (event_date)
) ENGINE=InnoDB;

-- One row per package in an order. Name and price are copied so later
-- menu changes never alter past orders.
CREATE TABLE order_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED NOT NULL,
    package_id    INT UNSIGNED NULL,
    package_name  VARCHAR(60)  NOT NULL,
    price_per_pax DECIMAL(6,2) NOT NULL,
    guests        SMALLINT UNSIGNED NOT NULL,
    line_total    DECIMAL(9,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- The dishes chosen for each order item.
CREATE TABLE order_item_dishes (
    order_item_id INT UNSIGNED NOT NULL,
    dish_id       INT UNSIGNED NULL,
    dish_name     VARCHAR(80)  NOT NULL,
    category_name VARCHAR(40)  NOT NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (dish_id)       REFERENCES dishes(id)      ON DELETE SET NULL
) ENGINE=InnoDB;

-- Every status change, shown as a timeline on the tracking page.
CREATE TABLE order_status_history (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    status     VARCHAR(20)  NOT NULL,
    note       VARCHAR(200) NOT NULL DEFAULT '',
    changed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Copy of every email the site sends (also useful when mail is not set up).
CREATE TABLE email_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NULL,
    to_email   VARCHAR(120) NOT NULL,
    subject    VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    sent_ok    TINYINT(1)   NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Feedback and staff
-- ---------------------------------------------------------------------

CREATE TABLE feedback (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(60)  NOT NULL,
    email      VARCHAR(120) NOT NULL,
    order_ref  CHAR(9)      NULL,
    rating     TINYINT UNSIGNED NOT NULL,
    message    VARCHAR(1000) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(40)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
