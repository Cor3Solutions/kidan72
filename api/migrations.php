<?php
/* =========================================================
   KIDAN 72 — Database upgrades
   Each number is one upgrade. They run automatically, once,
   the next time any page talks to the database, so an existing
   database (on your computer or on the host) keeps all its
   content and orders. Nothing to run by hand.
   ========================================================= */

if (!function_exists('kidan_add_column')) {
    function kidan_has_column(PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    }
    function kidan_add_column(PDO $pdo, string $table, string $column, string $definition): void {
        if (!kidan_has_column($pdo, $table, $column)) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

return [

    /* 2 — sold out switch, delivery fee / discount / payment on orders,
           admin email for password resets, activity log, password resets,
           order alert email address */
    2 => function (PDO $pdo): void {
        kidan_add_column($pdo, 'products', 'sold_out', "TINYINT(1) NOT NULL DEFAULT 0 AFTER sold_separately");

        $hadSubtotal = kidan_has_column($pdo, 'orders', 'subtotal');
        kidan_add_column($pdo, 'orders', 'subtotal', "DECIMAL(12,2) NULL AFTER item_count");
        kidan_add_column($pdo, 'orders', 'delivery_fee', "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER subtotal");
        kidan_add_column($pdo, 'orders', 'discount', "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER delivery_fee");
        kidan_add_column($pdo, 'orders', 'payment_method', "VARCHAR(40) NOT NULL DEFAULT '' AFTER total");
        kidan_add_column($pdo, 'orders', 'payment_ref', "VARCHAR(80) NOT NULL DEFAULT '' AFTER payment_method");
        if (!$hadSubtotal) $pdo->exec('UPDATE orders SET subtotal = total');

        kidan_add_column($pdo, 'admins', 'email', "VARCHAR(160) NOT NULL DEFAULT '' AFTER display_name");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_log (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_id    INT UNSIGNED NULL,
            admin_name  VARCHAR(80)  NOT NULL DEFAULT '',
            action      VARCHAR(255) NOT NULL,
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            token_hash  CHAR(64)     NOT NULL PRIMARY KEY,
            admin_id    INT UNSIGNED NOT NULL,
            expires_at  DATETIME     NOT NULL,
            used        TINYINT(1)   NOT NULL DEFAULT 0,
            KEY idx_admin (admin_id),
            CONSTRAINT fk_pr_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // New orders are emailed to the shop's contact email unless changed in the admin page
        $pdo->exec("INSERT IGNORE INTO settings (k, v) SELECT 'order_alert_email', v FROM settings WHERE k = 'contact_email'");
        $pdo->exec("INSERT IGNORE INTO settings (k, v) VALUES ('order_alert_email', '')");
    },

    /* 3 — CRM: customers, notes timeline, follow-ups; orders keep the
           address as province / city / barangay / street */
    3 => function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(120) NOT NULL,
            mobile        VARCHAR(40)  NOT NULL DEFAULT '',
            mobile_key    VARCHAR(20)  NULL UNIQUE,          -- 09171234567 (for matching repeat customers)
            email         VARCHAR(160) NOT NULL DEFAULT '',
            type          VARCHAR(30)  NOT NULL DEFAULT 'Individual',
            organization  VARCHAR(160) NOT NULL DEFAULT '',  -- office, school, barangay ...
            province      VARCHAR(80)  NOT NULL DEFAULT '',
            city          VARCHAR(80)  NOT NULL DEFAULT '',
            barangay      VARCHAR(120) NOT NULL DEFAULT '',
            street        VARCHAR(255) NOT NULL DEFAULT '',
            follow_up_on  DATE         NULL,
            follow_up_note VARCHAR(255) NOT NULL DEFAULT '',
            source        VARCHAR(20)  NOT NULL DEFAULT 'website',  -- website / admin
            created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_location (province, city),
            KEY idx_follow (follow_up_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_notes (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            admin_id    INT UNSIGNED NULL,
            admin_name  VARCHAR(80)  NOT NULL DEFAULT '',
            kind        VARCHAR(20)  NOT NULL DEFAULT 'note',    -- note / call / message / visit
            note        TEXT         NOT NULL,
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_customer (customer_id, created_at),
            CONSTRAINT fk_cn_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        kidan_add_column($pdo, 'orders', 'customer_id', "INT UNSIGNED NULL AFTER order_no");
        kidan_add_column($pdo, 'orders', 'province', "VARCHAR(80) NOT NULL DEFAULT '' AFTER address");
        kidan_add_column($pdo, 'orders', 'city', "VARCHAR(80) NOT NULL DEFAULT '' AFTER province");
        kidan_add_column($pdo, 'orders', 'barangay', "VARCHAR(120) NOT NULL DEFAULT '' AFTER city");
        kidan_add_column($pdo, 'orders', 'street', "VARCHAR(255) NOT NULL DEFAULT '' AFTER barangay");
        $st = $pdo->prepare("SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'orders' AND constraint_name = 'fk_orders_customer'");
        $st->execute();
        if (!$st->fetchColumn()) {
            $pdo->exec('ALTER TABLE orders ADD KEY idx_customer (customer_id), ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL');
        }

        // Existing orders: one customer per mobile number (old free-text address goes in "street")
        $orders = $pdo->query('SELECT id, customer, mobile, address FROM orders WHERE customer_id IS NULL ORDER BY id')->fetchAll();
        foreach ($orders as $o) {
            $cid = kidan_customer_for_order($pdo, $o['customer'], $o['mobile'], ['street' => $o['address']]);
            $pdo->prepare('UPDATE orders SET customer_id = ?, street = IF(street = \'\', ?, street) WHERE id = ?')->execute([$cid, $o['address'], $o['id']]);
        }
    },

    /* 4 — customer confirmations: email on the order, a private link to
           follow the order, the history of status changes, payment details */
    4 => function (PDO $pdo): void {
        kidan_add_column($pdo, 'orders', 'email', "VARCHAR(160) NOT NULL DEFAULT '' AFTER mobile");
        kidan_add_column($pdo, 'orders', 'track_token', "CHAR(32) NULL AFTER ip");
        $pdo->exec("UPDATE orders SET track_token = MD5(CONCAT(id, RAND(), NOW(6))) WHERE track_token IS NULL");
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_log (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id    INT UNSIGNED NOT NULL,
            status      VARCHAR(20)  NOT NULL,
            by_name     VARCHAR(80)  NOT NULL DEFAULT '',
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_order (order_id, created_at),
            CONSTRAINT fk_osl_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Existing orders start their history with "received" (and their current status if later)
        $pdo->exec("INSERT INTO order_status_log (order_id, status, created_at)
                    SELECT o.id, 'new', o.created_at FROM orders o WHERE NOT EXISTS (SELECT 1 FROM order_status_log l WHERE l.order_id = o.id)");
        $pdo->exec("INSERT INTO order_status_log (order_id, status, created_at)
                    SELECT o.id, o.status, o.updated_at FROM orders o WHERE o.status <> 'new'
                      AND NOT EXISTS (SELECT 1 FROM order_status_log l WHERE l.order_id = o.id AND l.status = o.status)");
        $pdo->exec("INSERT IGNORE INTO settings (k, v) VALUES ('payment_instructions', '')");
    },

    /* 5 — proof of payment uploaded by the customer on the tracking page,
           and text-message (SMS) alerts to the shop's phone */
    5 => function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_proofs (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id    INT UNSIGNED NOT NULL,
            file        VARCHAR(160) NOT NULL,              -- inside uploads/payments/ (never public)
            mime        VARCHAR(40)  NOT NULL DEFAULT 'image/jpeg',
            ref         VARCHAR(80)  NOT NULL DEFAULT '',   -- GCash / bank reference the customer typed
            amount      DECIMAL(12,2) NULL,
            note        VARCHAR(255) NOT NULL DEFAULT '',
            ip          VARCHAR(45)  NOT NULL DEFAULT '',
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_order (order_id, created_at),
            CONSTRAINT fk_pp_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['sms_numbers', 'sms_api_key', 'sms_sender', 'sms_last'] as $k) {
            $pdo->prepare("INSERT IGNORE INTO settings (k, v) VALUES (?, '')")->execute([$k]);
        }
    },

    /* 6 — email sending through an email account (SMTP) and a log of
           every email the system sends (shown on the order page) */
    6 => function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_log (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id    INT UNSIGNED NULL,
            to_addr     VARCHAR(160) NOT NULL,
            subject     VARCHAR(200) NOT NULL,
            ok          TINYINT(1)   NOT NULL DEFAULT 0,
            error       VARCHAR(255) NOT NULL DEFAULT '',
            via         VARCHAR(10)  NOT NULL DEFAULT '',   -- smtp / server
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_order (order_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pass', 'mail_from_name'] as $k) {
            $pdo->prepare("INSERT IGNORE INTO settings (k, v) VALUES (?, '')")->execute([$k]);
        }
    },

    /* 7 — "Keep me logged in on this device" for the admin page */
    7 => function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_remember (
            selector       CHAR(24)     NOT NULL PRIMARY KEY,
            validator_hash CHAR(64)     NOT NULL,
            admin_id       INT UNSIGNED NOT NULL,
            expires_at     DATETIME     NOT NULL,
            device         VARCHAR(160) NOT NULL DEFAULT '',
            created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_admin (admin_id),
            CONSTRAINT fk_ar_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    /* 8 — Kit revision (Oct 2026):
           - KIDAN Black + KIDAN Brown become one item, the KIDAN Pouch
             (K72-PCH-001), sold on its own; the customer picks Black or Brown
           - the Preparedness Bag holds every item plus 1 pouch (colour choice)
           - Shelf-Stable Food (MRE) is now the Food Pack
           - Collapsible Silicone Cup, Foldable Spoon and Glow Stick removed
           - Wipes: 1 pc
           Products can now have a choice (e.g. colour): products.choice */
    8 => function (PDO $pdo): void {
        kidan_add_column($pdo, 'products', 'choice', "TEXT NULL AFTER parts");
        $colour = json_encode(['label' => 'Colour', 'values' => ['Black', 'Brown'], 'photos' => [1, 2]]);
        $pouchColour = json_encode(['label' => 'Pouch colour', 'values' => ['Black', 'Brown']]);
        $has = fn(string $sn) => (bool)$pdo->query('SELECT COUNT(*) FROM products WHERE serial = ' . $pdo->quote($sn))->fetchColumn();

        $pdo->exec("INSERT IGNORE INTO categories (code, name, sort) VALUES ('PCH', 'Pouch', 5)");

        // The KIDAN Black becomes the KIDAN Pouch (keeps its price); the KIDAN Brown goes
        if ($has('K72-BAG-001') && !$has('K72-PCH-001')) {
            $pdo->exec("DELETE FROM bag_contents WHERE bag_serial IN ('K72-BAG-001', 'K72-BAG-002')");
            $pdo->exec("UPDATE products SET serial = 'K72-PCH-001' WHERE serial = 'K72-BAG-001'");
            $pdo->prepare("UPDATE products SET kind = 'item', cat = 'PCH', icon = 'bag', name = 'KIDAN Pouch', label = '',
                    short_text = ?, summary = '', why = ?, steps = ?, spec = ?, maintain = ?, parts = NULL, specs = NULL,
                    search_q = ?, photos = 3, photo_hidden = 0, sold_separately = 1, sort = 5, choice = ?
                    WHERE serial = 'K72-PCH-001'")
                ->execute([
                    'Organizer pouch for your kit. Choose black or brown.',
                    'A pouch keeps small items sorted, dry and easy to find in the dark, and you can grab it on its own when you need to move fast.',
                    json_encode(['Pack the items you use most at the top so you can reach them quickly.', 'Close every zip and buckle before you carry it.', 'Clip it to your bag or carry it on its own.']),
                    'Organizer pouch · Black or brown', 'Empty and air it out every 3 months. Check the zips and straps.',
                    'tactical organizer pouch', $colour,
                ]);
        }
        $pdo->exec("DELETE FROM products WHERE serial = 'K72-BAG-002'");

        // Removed items (their links to bags, training and photos go with them)
        $pdo->exec("DELETE FROM products WHERE serial IN ('K72-WTR-002', 'K72-FOD-006', 'K72-LGT-003')");

        // The Preparedness Bag: every item + 1 pouch
        if ($has('K72-BAG-000')) {
            $pdo->exec("DELETE FROM bag_contents WHERE bag_serial = 'K72-BAG-000'");
            $pdo->exec("INSERT INTO bag_contents (bag_serial, child_serial, sort)
                        SELECT 'K72-BAG-000', serial, sort FROM products WHERE kind = 'item'");
            $pdo->prepare("UPDATE products SET short_text = ?, summary = ?, specs = ?, choice = ? WHERE serial = 'K72-BAG-000'")
                ->execute([
                    'The full 72-hour system: the primary bag with every item and one KIDAN Pouch in black or brown.',
                    'Everything one person needs for the first 72 hours after a typhoon, flood or evacuation, packed in one bag with a KIDAN Pouch to keep the small items sorted. Choose a black or brown pouch.',
                    json_encode([['Configuration', 'Primary bag + 1 KIDAN Pouch (black or brown)'], ['Coverage', '1 person · 72 hours'], ['Contents', 'Every item in the KIDAN 72 system'],
                                 ['Registration', 'Logged in the KIDAN 72 Database with owner profile, inventory and expiry dates'], ['Inspection', 'Monthly function check · Quarterly full inspection']]),
                    $pouchColour,
                ]);
        }

        // The MRE is now the Food Pack
        $pdo->prepare("UPDATE products SET name = 'Food Pack', short_text = ?, spec = ?, search_q = ?,
                       steps = REPLACE(steps, 'MRE', 'food pack') WHERE serial = 'K72-FOD-001' AND name LIKE '%MRE%'")
            ->execute(['Ready-to-eat food for the first days, no cooking needed.', 'Ready-to-eat food pack', 'emergency food pack ready to eat']);

        // Wipes: just 1 pc (one pack)
        $pdo->prepare("UPDATE products SET name = 'Wipes (1 pc)', short_text = ?, spec = ? WHERE serial = 'K72-HYG-004' AND name = 'Wipes'")
            ->execute(['Mini wet wipes. Comes as 1 pc (one pack); the photo shows more than one.', 'Mini wet wipes · 1 pc (one pack)']);

        // Item texts that named the old pouches
        $pdo->exec("UPDATE products SET short_text = REPLACE(short_text, 'included in the KIDAN Black', 'included in the Preparedness Bag')
                    WHERE short_text LIKE '%included in the KIDAN Black%'");

        // Photo captions (only the ones nobody has changed)
        $cap = $pdo->prepare('UPDATE kit_slides SET caption = ? WHERE caption = ?');
        $cap->execute(['KIDAN Pouch · Black', 'KIDAN Black · all items']);
        $cap->execute(['KIDAN Pouch · Brown', 'KIDAN Brown · all items']);
        $cap->execute(['Preparedness Bag · with the KIDAN Pouch', 'Preparedness Bag · with both pouches']);
    },

    /* 9 — Stock (inventory): how many of each item are on hand, per colour
           for products with a choice, and every change (received, sold,
           returned, counted, damaged). An item is only tracked once a count
           has been entered for it (products.track_stock). */
    9 => function (PDO $pdo): void {
        kidan_add_column($pdo, 'products', 'track_stock', "TINYINT(1) NOT NULL DEFAULT 0 AFTER sold_out");
        kidan_add_column($pdo, 'products', 'low_stock', "INT NOT NULL DEFAULT 5 AFTER track_stock");
        kidan_add_column($pdo, 'orders', 'stock_taken', "TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        kidan_add_column($pdo, 'order_items', 'choice', "VARCHAR(40) NOT NULL DEFAULT '' AFTER name");
        $pdo->exec("CREATE TABLE IF NOT EXISTS stock (
            serial      VARCHAR(20)  NOT NULL,
            variant     VARCHAR(40)  NOT NULL DEFAULT '',   -- e.g. Black / Brown; '' for items without a choice
            qty         INT          NOT NULL DEFAULT 0,
            updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (serial, variant),
            CONSTRAINT fk_stock_product FOREIGN KEY (serial) REFERENCES products(serial) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_moves (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            serial      VARCHAR(20)  NOT NULL,
            variant     VARCHAR(40)  NOT NULL DEFAULT '',
            change_qty  INT          NOT NULL,
            qty_after   INT          NOT NULL,
            reason      VARCHAR(20)  NOT NULL,              -- received / sold / returned / count / damaged / other
            order_id    INT UNSIGNED NULL,
            note        VARCHAR(255) NOT NULL DEFAULT '',
            by_name     VARCHAR(80)  NOT NULL DEFAULT '',
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_serial (serial, variant, created_at),
            KEY idx_order (order_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // The colour of earlier order lines, e.g. "KIDAN Pouch (Brown)" → Brown
        $pdo->exec("UPDATE order_items SET choice = SUBSTRING_INDEX(SUBSTRING_INDEX(name, '(', -1), ')', 1)
                    WHERE choice = '' AND name REGEXP '[(](Black|Brown)[)]$'");
    },

    /* 10 — Product change: the Preparedness Bag is one big backpack with
            1 KIDAN Pouch inside, in Black, Brown or Green (the pouch is the
            same colour as the backpack). The bag photo changes with the colour.
            - New part K72-PCH-002 KIDAN Backpack (comes with the bag only),
              so the backpacks can be counted per colour in Stock
            - KIDAN Pouch: Black, Brown or Green (photo 4 = inside)
            Photos: K72-BAG-000.jpg black, -2 brown, -3 green */
    10 => function (PDO $pdo): void {
        $colours = ['Black', 'Brown', 'Green'];
        $colour = json_encode(['label' => 'Colour', 'values' => $colours, 'photos' => [1, 2, 3]]);
        $has = fn(string $sn) => (bool)$pdo->query('SELECT COUNT(*) FROM products WHERE serial = ' . $pdo->quote($sn))->fetchColumn();

        $pdo->exec("UPDATE categories SET name = 'Backpack & Pouch' WHERE code = 'PCH' AND name = 'Pouch'");

        if (!$has('K72-PCH-002')) {
            $pdo->prepare("INSERT INTO products (serial, kind, cat, icon, name, label, short_text, summary, why, steps, spec, maintain, choice, search_q, photos, sold_separately, sort)
                           VALUES ('K72-PCH-002', 'item', 'PCH', 'bag', 'KIDAN Backpack', '', ?, '', ?, ?, ?, ?, ?, ?, 3, 0, 6)")
                ->execute([
                    'One big backpack that carries the whole 72-hour kit. Black, brown or green.',
                    'One bag you can grab and carry with both hands free. Everything you need for the first 72 hours is packed inside, with the KIDAN Pouch for the small items.',
                    json_encode(['Keep it packed and ready near the door.', 'Put the heavy items close to your back and the things you need first at the top.', 'Tighten the shoulder and chest straps so it sits close to your body.']),
                    'Large tactical backpack · Black, brown or green',
                    'Empty and air it out every 3 months. Check the zips, buckles and straps.',
                    $colour, 'tactical backpack go bag',
                ]);
        }

        if ($has('K72-PCH-001')) {
            $pdo->prepare("UPDATE products SET choice = ?, photos = GREATEST(photos, 4),
                           short_text = REPLACE(short_text, 'Choose black or brown.', 'Choose black, brown or green.'),
                           spec = REPLACE(spec, 'Black or brown', 'Black, brown or green')
                           WHERE serial = 'K72-PCH-001'")->execute([$colour]);
        }

        if ($has('K72-BAG-000')) {
            $pdo->exec("INSERT IGNORE INTO bag_contents (bag_serial, child_serial, sort) VALUES ('K72-BAG-000', 'K72-PCH-002', 0)");
            $pdo->prepare("UPDATE products SET choice = ?, photos = GREATEST(photos, 3), short_text = ?, summary = ?, specs = ? WHERE serial = 'K72-BAG-000'")
                ->execute([
                    $colour,
                    'The full 72-hour system: one big backpack with every item and a KIDAN Pouch inside. Black, brown or green.',
                    'Everything one person needs for the first 72 hours after a typhoon, flood or evacuation, packed in one big backpack with a KIDAN Pouch inside to keep the small items sorted. Choose black, brown or green; the pouch comes in the same colour.',
                    json_encode([['Configuration', '1 big backpack + 1 KIDAN Pouch inside, same colour'], ['Colours', 'Black · Brown · Green'], ['Coverage', '1 person · 72 hours'], ['Contents', 'Every item in the KIDAN 72 system'],
                                 ['Registration', 'Logged in the KIDAN 72 Database with owner profile, inventory and expiry dates'], ['Inspection', 'Monthly function check · Quarterly full inspection']]),
                ]);
        }
    },

    /* 11 — Login levels: Super admin (everything), Admin (the day-to-day
            work: orders, customers, products, stock, reports) and Encoder
            (only enters products, stock and training content: no orders,
            customers, sales or reports). Logins that already exist become
            Super admin, so nobody loses access. */
    11 => function (PDO $pdo): void {
        $has = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admins' AND column_name = 'role'")->fetchColumn();
        if (!$has) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(16) NOT NULL DEFAULT 'admin' AFTER email");
            $pdo->exec("UPDATE admins SET role = 'super'");
        }
    },

];
