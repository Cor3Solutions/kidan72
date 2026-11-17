-- =========================================================
--  KIDAN 72 — Database tables (MySQL 5.7+ / MariaDB 10.3+)
--  You do not need to run this by hand: admin/setup.php
--  creates these tables for you on the first visit.
-- =========================================================

SET NAMES utf8mb4;

-- Contact details and other single values (messenger, email, brand film ...)
CREATE TABLE IF NOT EXISTS settings (
  k           VARCHAR(40)  NOT NULL PRIMARY KEY,
  v           TEXT         NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item categories (WTR = Water & Hydration ...)
CREATE TABLE IF NOT EXISTS categories (
  code        VARCHAR(8)   NOT NULL PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  sort        INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every bag and every item, one row per serial number.
-- Lists (steps, parts, specs) are stored as JSON text.
CREATE TABLE IF NOT EXISTS products (
  serial          VARCHAR(20)   NOT NULL PRIMARY KEY,
  kind            ENUM('bag','item') NOT NULL,
  cat             VARCHAR(8)    NULL,          -- items only
  icon            VARCHAR(30)   NOT NULL DEFAULT '',
  name            VARCHAR(120)  NOT NULL,
  label           VARCHAR(80)   NOT NULL DEFAULT '',   -- bags only ("Complete system")
  short_text      TEXT          NOT NULL,
  summary         TEXT          NOT NULL,      -- bags only
  why             TEXT          NOT NULL,      -- items only
  steps           TEXT          NULL,          -- JSON list of strings
  spec            TEXT          NOT NULL,
  maintain        TEXT          NOT NULL,
  parts           TEXT          NULL,          -- JSON list of strings (kits)
  specs           TEXT          NULL,          -- JSON list of [label, value] (bags)
  search_q        VARCHAR(200)  NOT NULL DEFAULT '',
  video           VARCHAR(255)  NOT NULL DEFAULT '',
  price           DECIMAL(10,2) NULL,          -- pesos; NULL shows "PHP xx"
  photos          TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- how many photos
  photo_hidden    TINYINT(1)    NOT NULL DEFAULT 0,      -- 1 = show "Photo coming soon"
  sold_separately TINYINT(1)    NOT NULL DEFAULT 1,
  sort            INT           NOT NULL DEFAULT 0,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_kind_sort (kind, sort),
  CONSTRAINT fk_products_cat FOREIGN KEY (cat) REFERENCES categories(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos uploaded through the admin page (saved in /uploads/products/).
-- When a product has no uploaded photo, the site uses
-- assets/img/products/<serial>.jpg as before.
CREATE TABLE IF NOT EXISTS product_photos (
  serial      VARCHAR(20)  NOT NULL,
  position    TINYINT UNSIGNED NOT NULL,       -- 1 = main photo, 2 = second ...
  file        VARCHAR(160) NOT NULL,           -- path relative to the site
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (serial, position),
  CONSTRAINT fk_photos_product FOREIGN KEY (serial) REFERENCES products(serial) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What goes inside each bag. A child can be an item (pouch contents)
-- or another bag (the Preparedness Bag holds KIDAN Black + KIDAN Brown).
CREATE TABLE IF NOT EXISTS bag_contents (
  bag_serial   VARCHAR(20) NOT NULL,
  child_serial VARCHAR(20) NOT NULL,
  sort         INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (bag_serial, child_serial),
  CONSTRAINT fk_bc_bag   FOREIGN KEY (bag_serial)   REFERENCES products(serial) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bc_child FOREIGN KEY (child_serial) REFERENCES products(serial) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training modules A to J
CREATE TABLE IF NOT EXISTS modules (
  code        VARCHAR(4)   NOT NULL PRIMARY KEY,
  icon        VARCHAR(30)  NOT NULL DEFAULT '',
  name        VARCHAR(120) NOT NULL,
  short_text  TEXT         NOT NULL,
  lessons     TEXT         NULL,               -- JSON list of strings
  search_q    VARCHAR(200) NOT NULL DEFAULT '',
  video       VARCHAR(255) NOT NULL DEFAULT '',
  sort        INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which bags/items each training module covers
CREATE TABLE IF NOT EXISTS module_items (
  module_code VARCHAR(4)  NOT NULL,
  serial      VARCHAR(20) NOT NULL,
  sort        INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (module_code, serial),
  CONSTRAINT fk_mi_module  FOREIGN KEY (module_code) REFERENCES modules(code) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mi_product FOREIGN KEY (serial)      REFERENCES products(serial) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Readiness Check questions
CREATE TABLE IF NOT EXISTS readiness (
  code        VARCHAR(10)  NOT NULL PRIMARY KEY,
  text        VARCHAR(255) NOT NULL,
  module_code VARCHAR(4)   NULL,
  sort        INT          NOT NULL DEFAULT 0,
  CONSTRAINT fk_rd_module FOREIGN KEY (module_code) REFERENCES modules(code) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kit page slideshow
CREATE TABLE IF NOT EXISTS kit_slides (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  src         VARCHAR(160) NOT NULL,
  fallback    VARCHAR(160) NOT NULL DEFAULT '',
  caption     VARCHAR(160) NOT NULL DEFAULT '',
  sort        INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders sent from the My Order page
CREATE TABLE IF NOT EXISTS orders (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_no     VARCHAR(20)  NULL UNIQUE,       -- K72-0001
  customer     VARCHAR(120) NOT NULL,
  mobile       VARCHAR(40)  NOT NULL,
  address      VARCHAR(255) NOT NULL DEFAULT '',
  notes        TEXT         NOT NULL,
  channel      VARCHAR(20)  NOT NULL DEFAULT '',   -- website / messenger / email / viber / copy / phone / fb / walkin / event / other
  status       ENUM('new','confirmed','paid','delivered','cancelled') NOT NULL DEFAULT 'new',
  item_count   INT          NOT NULL DEFAULT 0,
  total        DECIMAL(12,2) NULL,             -- NULL when some prices were not set yet
  admin_note   TEXT         NOT NULL,
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The lines of each order. Name and price are copied at the time
-- of ordering, so later price changes do not change old orders.
CREATE TABLE IF NOT EXISTS order_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  serial      VARCHAR(20)  NOT NULL,
  name        VARCHAR(120) NOT NULL,
  qty         INT UNSIGNED NOT NULL,
  unit_price  DECIMAL(10,2) NULL,
  line_total  DECIMAL(12,2) NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- People who can log in to the admin page
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(40)  NOT NULL UNIQUE,
  display_name  VARCHAR(80)  NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  last_login    TIMESTAMP    NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed login attempts (slows down password guessing)
CREATE TABLE IF NOT EXISTS login_attempts (
  ip          VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
