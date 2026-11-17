<?php
/* KIDAN 72 — Makes the sample orders for Admin → More → Sample data.
   See admin/sample-data.php. No emails or text messages are sent. */

if (!defined('KIDAN_ROOT')) exit;

/* Makes the sample orders. Returns how many. */
function kidan_make_sample_orders(PDO $pdo, string $by): int {
    mt_srand(7272);
    $rand = fn(array $a) => $a[mt_rand(0, count($a) - 1)];

    // Products that can be ordered, with a sample price when none is set yet
    $samplePrice = ['K72-BAG-000' => 4500, 'K72-PCH-001' => 650, 'K72-WTR-001' => 180, 'K72-WTR-003' => 220, 'K72-FOD-001' => 95, 'K72-FOD-002' => 120,
                    'K72-FOD-003' => 40, 'K72-FOD-005' => 150, 'K72-MED-001' => 850, 'K72-LGT-001' => 390, 'K72-LGT-002' => 420, 'K72-PWR-001' => 990,
                    'K72-PWR-002' => 180, 'K72-PWR-003' => 1250, 'K72-COM-001' => 140, 'K72-COM-002' => 120, 'K72-DOC-001' => 60, 'K72-DOC-002' => 15,
                    'K72-DOC-003' => 90, 'K72-WTH-001' => 85, 'K72-WTH-002' => 150, 'K72-WTH-003' => 70, 'K72-WTH-004' => 1800, 'K72-WTH-005' => 1200,
                    'K72-HYG-001' => 160, 'K72-HYG-002' => 120, 'K72-HYG-003' => 45, 'K72-HYG-004' => 35, 'K72-TLS-002' => 130, 'K72-TLS-003' => 220,
                    'K72-TLS-004' => 60, 'K72-SRV-001' => 180, 'K72-SRV-002' => 250, 'K72-SRV-003' => 260, 'K72-SRV-004' => 280];
    $products = [];
    foreach ($pdo->query("SELECT serial, name, price, choice FROM products WHERE sold_separately = 1 ORDER BY sort, serial") as $p) {
        $price = $p['price'] !== null && (float)$p['price'] > 0 ? (float)$p['price'] : ($samplePrice[$p['serial']] ?? 150);
        $products[$p['serial']] = ['name' => $p['name'], 'price' => $price, 'choice' => product_choice($p['choice'] ?? null)];
    }
    if (!$products) throw new RuntimeException('There are no products to make sample orders with.');
    $bag = isset($products['K72-BAG-000']) ? 'K72-BAG-000' : null;
    $extras = array_values(array_diff(array_keys($products), [$bag]));
    // refill favourites come up more often
    $popular = array_values(array_intersect(['K72-PCH-001', 'K72-FOD-001', 'K72-FOD-002', 'K72-WTR-001', 'K72-MED-001', 'K72-PWR-001', 'K72-LGT-001', 'K72-HYG-004', 'K72-WTH-001', 'K72-WTH-002'], $extras));

    $places = [
        ['Laguna', ['Calamba City', 'Santa Rosa City', 'Biñan City', 'San Pablo City', 'Cabuyao City', 'Los Baños'], 100, 30],
        ['Metro Manila', ['Quezon City', 'Makati City', 'Pasig City', 'Taguig City', 'Manila', 'Muntinlupa City'], 150, 22],
        ['Batangas', ['Tanauan City', 'Santo Tomas City', 'Lipa City', 'Batangas City'], 150, 16],
        ['Cavite', ['Bacoor City', 'Dasmariñas City', 'Imus City', 'General Trias City'], 150, 14],
        ['Rizal', ['Antipolo City', 'Cainta', 'Taytay'], 150, 8],
        ['Quezon', ['Lucena City', 'Tayabas City'], 200, 5],
        ['Cebu', ['Cebu City', 'Mandaue City'], 350, 3],
        ['Davao del Sur', ['Davao City'], 400, 2],
    ];
    $first = ['Maria', 'Jose', 'Ana', 'Mark', 'Liza', 'Ramon', 'Grace', 'Paolo', 'Cris', 'Joy', 'Ben', 'Rina', 'Carlo', 'Mae', 'Jun', 'Aileen', 'Rafael', 'Kristine',
              'Noel', 'Bea', 'Arnel', 'Celia', 'Dennis', 'Lovely', 'Jerome', 'Tess', 'Mico', 'Pia', 'Ronald', 'Shiela'];
    $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Villanueva', 'Ramos', 'Aquino', 'Flores', 'Dela Cruz', 'Castillo',
             'Navarro', 'Gonzales', 'Lopez', 'Francisco', 'Mercado', 'Domingo'];
    $orgs = [['Office / Company', 'Laguna Tech Park Corp.'], ['School', 'San Isidro National High School'], ['Barangay / LGU', 'Barangay Bagong Kalsada'],
             ['Organization / NGO', 'Rotary Club of Calamba'], ['Office / Company', 'Southwoods Logistics'], ['Reseller', 'Ready PH Supplies'], ['Barangay / LGU', 'Barangay Real']];

    // About 70 customers: most are individuals, a few groups that order in bulk
    $customers = [];
    for ($i = 0; $i < 70; $i++) {
        [$prov, $cities, $fee, $w] = $places[0];
        $roll = mt_rand(1, 100); $acc = 0;
        foreach ($places as $pl) { $acc += $pl[3]; if ($roll <= $acc) { [$prov, $cities, $fee] = $pl; break; } }
        $org = $i % 10 === 3 ? $orgs[intdiv($i, 10) % count($orgs)] : null;
        $customers[] = ['name' => $rand($first) . ' ' . $rand($last), 'mobile' => sprintf('0900 %03d %04d', intdiv($i, 10) + 100, 1000 + $i * 37),
                        'type' => $org[0] ?? ($i % 7 === 5 ? 'Family' : 'Individual'), 'org' => $org[1] ?? '',
                        'province' => $prov, 'city' => $rand($cities), 'fee' => $fee, 'id' => null];
    }

    $channels = ['website', 'website', 'website', 'website', 'fb', 'fb', 'phone', 'walkin', 'event'];
    $methods = ['GCash', 'GCash', 'GCash', 'GCash', 'Maya', 'Bank transfer', 'Bank transfer', 'Cash on delivery'];
    $streets = ['Rizal St.', 'Mabini St.', 'Bonifacio Ave.', 'Del Pilar St.', 'Luna St.', 'Phase 2, Blk 4 Lot 12', 'Purok 3', 'Sampaguita St.'];
    $insC = $pdo->prepare("INSERT INTO customers (name, mobile, mobile_key, email, type, organization, province, city, barangay, street, source, created_at)
                           VALUES (?, ?, ?, '', ?, ?, ?, ?, '', ?, 'sample', ?)");
    $insO = $pdo->prepare("INSERT INTO orders (customer_id, customer, mobile, email, address, province, city, barangay, street, notes, channel, status, item_count,
                           subtotal, delivery_fee, discount, total, payment_method, payment_ref, admin_note, ip, track_token, created_at)
                           VALUES (?, ?, ?, '', ?, ?, ?, '', ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sample', ?, ?)");
    $insI = $pdo->prepare('INSERT INTO order_items (order_id, serial, name, choice, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insL = $pdo->prepare('INSERT INTO order_status_log (order_id, status, by_name, created_at) VALUES (?, ?, ?, ?)');
    $setNo = $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?');

    $pick = function (string $sn) use ($products, $rand) {
        $ch = $products[$sn]['choice'];
        $c = $ch ? $rand($ch['values']) : '';
        return [$sn, $products[$sn]['name'] . ($c !== '' ? " ($c)" : ''), $products[$sn]['price']];
    };

    $pdo->beginTransaction();
    $count = 0;
    $now = time();
    for ($day = 182; $day >= 0; $day--) {
        $ts = strtotime("-$day days", $now);
        // more orders as the months go on, and around typhoon season (Jul–Oct)
        $month = (int)date('n', $ts);
        $base = 0.35 + (182 - $day) / 182 * 0.9 + (in_array($month, [7, 8, 9, 10], true) ? 0.35 : 0);
        if (in_array(date('N', $ts), ['6', '7'], true)) $base += 0.25;
        $n = 0; for ($k = 0; $k < 3; $k++) if (mt_rand(0, 1000) / 1000 < $base / 3) $n++;
        for ($j = 0; $j < $n; $j++) {
            $ci = mt_rand(0, 100) < 30 && $count > 20 ? mt_rand(0, min(69, intdiv($count, 2))) : mt_rand(0, 69);   // some come back
            $c = &$customers[$ci];
            $when = date('Y-m-d H:i:s', strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d:%02d:00', mt_rand(7, 21), mt_rand(0, 59))));
            if (strtotime($when) > $now) $when = date('Y-m-d H:i:s', $now - mt_rand(600, 7200));
            if ($c['id'] === null) {
                $insC->execute([$c['name'], $c['mobile'], mobile_key($c['mobile']), $c['type'], $c['org'], $c['province'], $c['city'],
                                mt_rand(1, 99) . ' ' . $rand($streets), $when]);
                $c['id'] = (int)$pdo->lastInsertId();
            }
            // what they buy
            $lines = [];
            $bulk = $c['type'] !== 'Individual' && $c['type'] !== 'Family';
            if ($bag && mt_rand(1, 100) <= ($bulk ? 90 : 55)) $lines[] = [...$pick($bag), $bulk ? mt_rand(3, 12) : (mt_rand(1, 100) <= 85 ? 1 : 2)];
            $extraCount = $lines ? mt_rand(0, 2) : mt_rand(1, 4);
            for ($e = 0; $e < $extraCount; $e++) {
                $sn = mt_rand(1, 100) <= 60 && $popular ? $rand($popular) : $rand($extras);
                $lines[] = [...$pick($sn), mt_rand(1, 100) <= 70 ? 1 : mt_rand(2, 4)];
            }
            $merged = [];
            foreach ($lines as [$sn, $name, $price, $qty]) { $k = "$sn|$name"; $merged[$k] = isset($merged[$k]) ? [$sn, $name, $price, $merged[$k][3] + $qty] : [$sn, $name, $price, $qty]; }
            $sub = 0; $items = 0;
            foreach ($merged as [$sn, $name, $price, $qty]) { $sub += $price * $qty; $items += $qty; }
            $fee = $c['province'] === 'Laguna' && mt_rand(1, 100) <= 25 ? 0 : $c['fee'];   // some pick up
            $disc = $bulk && $sub >= 15000 ? round($sub * 0.05 / 50) * 50 : 0;
            // status: older orders are finished; the last two weeks are still moving
            $age = ($now - strtotime($when)) / 86400;
            $r = mt_rand(1, 100);
            if ($age > 14) $status = $r <= 90 ? 'delivered' : ($r <= 96 ? 'cancelled' : 'paid');
            elseif ($age > 5) $status = $r <= 55 ? 'delivered' : ($r <= 80 ? 'paid' : ($r <= 92 ? 'confirmed' : 'cancelled'));
            else $status = $r <= 35 ? 'new' : ($r <= 65 ? 'confirmed' : ($r <= 85 ? 'paid' : 'delivered'));
            $paid = in_array($status, ['paid', 'delivered'], true);
            $method = $paid ? $rand($methods) : '';
            $ref = $paid && $method !== 'Cash on delivery' ? sprintf('%04d %03d %06d', mt_rand(1000, 9999), mt_rand(100, 999), mt_rand(100000, 999999)) : '';
            $street = mt_rand(1, 99) . ' ' . $rand($streets);
            $insO->execute([$c['id'], $c['name'], $c['mobile'], "$street, {$c['city']}, {$c['province']}", $c['province'], $c['city'], $street,
                            $rand($channels), $status, $items, round($sub, 2), $fee, $disc, round($sub + $fee - $disc, 2), $method, $ref,
                            '[SAMPLE DATA] Made-up order for trying out the reports.', bin2hex(random_bytes(16)), $when]);
            $id = (int)$pdo->lastInsertId();
            $setNo->execute([sprintf('K72-%04d', $id), $id]);
            foreach ($merged as [$sn, $name, $price, $qty]) $insI->execute([$id, $sn, $name, preg_match('/\(([^()]+)\)$/', $name, $m) && $products[$sn]['choice'] ? $m[1] : '', $qty, $price, round($price * $qty, 2)]);
            // status history
            $steps = $status === 'cancelled' ? ['new', 'cancelled'] : array_slice(['new', 'confirmed', 'paid', 'delivered'], 0, array_search($status, ['new', 'confirmed', 'paid', 'delivered'], true) + 1);
            $t = strtotime($when);
            foreach ($steps as $i => $s) { $insL->execute([$id, $s, $i ? 'Sample' : 'Website', date('Y-m-d H:i:s', min($now, $t))]); $t += mt_rand(3, 30) * 3600; }
            unset($c);
            $count++;
        }
    }
    $pdo->commit();
    return $count;
}
