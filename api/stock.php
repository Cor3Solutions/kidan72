<?php
/* =========================================================
   KIDAN 72 — Stock (inventory)

   - Each item has a count on hand; a product with a choice (the
     KIDAN Backpack and KIDAN Pouch: Black / Brown / Green) has one count per colour.
   - An item is "tracked" once a count has been entered for it
     (products.track_stock = 1). Untracked items never show Sold out
     and are never counted down.
   - A bag (the Preparedness Bag) has no count of its own: selling one
     takes 1 of every item packed in it, plus the backpack and the pouch in the chosen colour.
     How many bags can still be packed = the item that runs out first.
   - Stock goes down when an order is Confirmed (or Paid / Delivered),
     and comes back when it is Cancelled, set back to New, or deleted.
     Sample orders never touch the stock.
   ========================================================= */

if (!defined('KIDAN_ROOT')) exit;

const STOCK_HOLD_STATUSES = ['confirmed', 'paid', 'delivered'];
const STOCK_REASONS = ['received' => 'Stock arrived', 'sold' => 'Sold (order confirmed)', 'returned' => 'Back in stock (order cancelled)',
                       'count' => 'Shelf count', 'damaged' => 'Removed', 'other' => 'Other change'];
/* Why items are removed (Stock → Remove items) */
const STOCK_REMOVE_WHY = ['Damaged', 'Expired', 'Lost or missing', 'Used for display / demo / training', 'Given away (donation / sample)', 'Other'];

/* Everything needed to work with stock, loaded once per request */
function stock_info(PDO $pdo, bool $fresh = false): array {
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    $products = [];
    foreach ($pdo->query('SELECT serial, kind, cat, name, choice, track_stock, low_stock, sold_out, sold_separately, price, sort FROM products ORDER BY kind = \'item\', sort, serial') as $p) {
        $p['choice'] = product_choice($p['choice'] ?? null);
        $products[$p['serial']] = $p;
    }
    $qty = [];
    foreach ($pdo->query('SELECT serial, variant, qty FROM stock') as $r) $qty[$r['serial']][$r['variant']] = (int)$r['qty'];
    $children = [];
    foreach ($pdo->query('SELECT bag_serial, child_serial FROM bag_contents ORDER BY sort, child_serial') as $r) $children[$r['bag_serial']][] = $r['child_serial'];
    return $cache = ['products' => $products, 'qty' => $qty, 'children' => $children];
}

/* The variants of a product: its choice values, or [''] */
function stock_variants(array $p): array {
    return $p['choice'] ? $p['choice']['values'] : [''];
}

/* On hand for an item (null = not tracked) */
function stock_on_hand(array $info, string $serial, string $variant = ''): ?int {
    $p = $info['products'][$serial] ?? null;
    if (!$p || !$p['track_stock']) return null;
    return $info['qty'][$serial][$variant] ?? 0;
}

/* What one unit of a product takes out of stock: [[serial, variant, qty], ...]
   A bag is broken down into what is packed in it; the bag's choice
   (pouch colour) goes to the packed item that has the same choice. */
function stock_parts(array $info, string $serial, string $variant = '', int $depth = 0): array {
    $p = $info['products'][$serial] ?? null;
    if (!$p) return [];
    if ($p['kind'] !== 'bag') return [[$serial, $p['choice'] ? $variant : '', 1]];
    if ($depth > 3) return [];
    $out = [];
    foreach ($info['children'][$serial] ?? [] as $child) {
        $c = $info['products'][$child] ?? null;
        if (!$c) continue;
        $v = $c['choice'] && in_array($variant, $c['choice']['values'], true) ? $variant : ($c['choice'] ? $c['choice']['values'][0] : '');
        foreach (stock_parts($info, $child, $v, $depth + 1) as $part) $out[] = $part;
    }
    return $out;
}

/* How many of a product can be sold right now (null = not tracked).
   For a bag: how many can still be packed, and which item runs out first. */
function stock_available(array $info, string $serial, string $variant = ''): ?array {
    $p = $info['products'][$serial] ?? null;
    if (!$p) return null;
    $min = null; $limit = '';
    $need = [];
    foreach (stock_parts($info, $serial, $variant) as [$s, $v, $q]) $need["$s|$v"] = ($need["$s|$v"] ?? 0) + $q;
    foreach ($need as $k => $q) {
        [$s, $v] = explode('|', $k, 2);
        $have = stock_on_hand($info, $s, $v);
        if ($have === null) continue;
        $can = intdiv(max(0, $have), $q);
        if ($min === null || $can < $min) { $min = $can; $limit = $info['products'][$s]['name'] . ($v !== '' ? " ($v)" : ''); }
    }
    return $min === null ? null : ['qty' => $min, 'limit' => $limit];
}

/* Changes the count of one item and writes it in the history */
function stock_move(PDO $pdo, string $serial, string $variant, int $change, string $reason, string $note = '', ?int $orderId = null, string $by = '', ?int $setTo = null): int {
    $st = $pdo->prepare('SELECT qty FROM stock WHERE serial = ? AND variant = ? FOR UPDATE');
    $st->execute([$serial, $variant]);
    $now = $st->fetchColumn();
    $now = $now === false ? 0 : (int)$now;
    $after = $setTo ?? $now + $change;
    $change = $after - $now;
    $pdo->prepare('INSERT INTO stock (serial, variant, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = VALUES(qty)')->execute([$serial, $variant, $after]);
    $pdo->prepare('UPDATE products SET track_stock = 1 WHERE serial = ? AND track_stock = 0')->execute([$serial]);
    $pdo->prepare('INSERT INTO stock_moves (serial, variant, change_qty, qty_after, reason, order_id, note, by_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$serial, $variant, $change, $after, $reason, $orderId, mb_substr($note, 0, 255), mb_substr($by, 0, 80)]);
    return $after;
}

/* The stock an order takes: [serial|variant => qty] (tracked items only) */
function stock_order_needs(PDO $pdo, array $info, int $orderId): array {
    $st = $pdo->prepare('SELECT serial, choice, name, qty FROM order_items WHERE order_id = ?');
    $st->execute([$orderId]);
    $need = [];
    foreach ($st as $l) {
        $variant = (string)$l['choice'];
        if ($variant === '' && preg_match('/\(([^()]+)\)$/', $l['name'], $m)) $variant = $m[1];   // lines saved before colours were stored
        foreach (stock_parts($info, $l['serial'], $variant) as [$s, $v, $q]) {
            if (stock_on_hand($info, $s, $v) === null) continue;
            $need["$s|$v"] = ($need["$s|$v"] ?? 0) + $q * (int)$l['qty'];
        }
    }
    return $need;
}

/* Puts the order's stock in line with its status. Returns what happened:
   ['taken' => n, 'returned' => n, 'short' => ['First Aid Kit (2 short)', ...]] */
function stock_sync_order(PDO $pdo, int $orderId, string $by = ''): array {
    $res = ['taken' => 0, 'returned' => 0, 'short' => []];
    $st = $pdo->prepare('SELECT id, order_no, status, stock_taken, ip FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o || $o['ip'] === 'sample') return $res;
    $hold = in_array($o['status'], STOCK_HOLD_STATUSES, true);
    if ($hold === (bool)$o['stock_taken']) return $res;
    $info = stock_info($pdo, true);
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        if ($hold) {
            foreach (stock_order_needs($pdo, $info, $orderId) as $k => $q) {
                [$s, $v] = explode('|', $k, 2);
                $after = stock_move($pdo, $s, $v, -$q, 'sold', "Order {$o['order_no']}", $orderId, $by);
                $res['taken'] += $q;
                if ($after < 0) $res['short'][] = $info['products'][$s]['name'] . ($v !== '' ? " ($v)" : '') . ' (' . -$after . ' short)';
            }
        } else {
            // give back exactly what this order took (also items no longer tracked)
            $st = $pdo->prepare("SELECT serial, variant, SUM(change_qty) AS q FROM stock_moves WHERE order_id = ? AND reason IN ('sold', 'returned') GROUP BY serial, variant HAVING q < 0");
            $st->execute([$orderId]);
            foreach ($st->fetchAll() as $m) {
                stock_move($pdo, $m['serial'], $m['variant'], -(int)$m['q'], 'returned', "Order {$o['order_no']} " . ($o['status'] === 'cancelled' ? 'cancelled' : 'back to New'), $orderId, $by);
                $res['returned'] += -(int)$m['q'];
            }
        }
        $pdo->prepare('UPDATE orders SET stock_taken = ? WHERE id = ?')->execute([$hold ? 1 : 0, $orderId]);
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    stock_info($pdo, true);
    return $res;
}

/* Checks a new order list against the stock. Returns '' or a message for the customer.
   $want: [serial|choice => qty] as used by kidan_create_order */
function stock_check_order(PDO $pdo, array $want): string {
    $info = stock_info($pdo);
    $need = [];
    foreach ($want as $key => $qty) {
        [$sn, $pick] = array_pad(explode('|', (string)$key, 2), 2, '');
        foreach (stock_parts($info, $sn, $pick) as [$s, $v, $q]) {
            if (stock_on_hand($info, $s, $v) === null) continue;
            $need["$s|$v"] = ($need["$s|$v"] ?? 0) + $q * $qty;
        }
    }
    foreach ($need as $k => $q) {
        [$s, $v] = explode('|', $k, 2);
        $have = max(0, (int)stock_on_hand($info, $s, $v));
        if ($q > $have) {
            $name = $info['products'][$s]['name'] . ($v !== '' ? " ($v)" : '');
            return $have === 0
                ? "Sorry, the $name is out of stock right now. Please remove it or choose another option."
                : "Sorry, we only have $have of the $name left. Please lower the quantity.";
        }
    }
    return '';
}

/* For the website: what is sold out (by colour too) and what is running low */
function stock_public(PDO $pdo): array {
    $info = stock_info($pdo);
    $soldOut = []; $choiceOut = []; $left = [];
    foreach ($info['products'] as $s => $p) {
        if (!$p['sold_separately']) continue;
        $vals = stock_variants($p);
        $out = []; $min = null; $tracked = false;
        foreach ($vals as $v) {
            $a = stock_available($info, $s, $v);
            if ($a === null) continue;
            $tracked = true;
            if ($a['qty'] <= 0) $out[] = $v; else $min = $min === null ? $a['qty'] : min($min, $a['qty']);
        }
        if (!$tracked) continue;
        if (count($out) === count($vals)) { $soldOut[] = $s; continue; }
        if ($p['choice'] && $out) $choiceOut[$s] = $out;
        if ($min !== null && $min <= max(0, (int)$p['low_stock'])) $left[$s] = $min;
    }
    return ['soldOut' => $soldOut, 'choiceOut' => $choiceOut, 'left' => $left];
}

/* Items at or below their low-stock number (for the admin Home page) */
function stock_low_count(PDO $pdo): int {
    $info = stock_info($pdo);
    $n = 0;
    foreach ($info['products'] as $s => $p) {
        if (!$p['track_stock'] || $p['kind'] === 'bag') continue;
        foreach (stock_variants($p) as $v) if (($info['qty'][$s][$v] ?? 0) <= (int)$p['low_stock']) { $n++; break; }
    }
    return $n;
}
