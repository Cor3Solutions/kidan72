<?php
/* =========================================================
   KIDAN 72 — Item page with its own Facebook / Messenger preview

   Facebook does not run the page's JavaScript, so every shared
   item.html?sn=... link used to show the same generic preview.
   On a PHP host, .htaccess sends item.html?sn=... here: this file
   serves the normal item.html, with the title, description and
   photo of that bag or item filled in for link previews.
   Nothing else on the page changes.
   ========================================================= */

declare(strict_types=1);

$html = (string)file_get_contents(__DIR__ . '/item.html');
$sn = strtoupper(trim((string)($_GET['sn'] ?? '')));

header('Content-Type: text/html; charset=utf-8');

$p = null;
if (preg_match('/^K72-[A-Z]{3}-\d{3}$/', $sn) && is_file(__DIR__ . '/api/config.php')) {
    try {
        require __DIR__ . '/api/lib.php';
        $st = db()->prepare('SELECT serial, kind, name, short_text, summary, photo_hidden FROM products WHERE serial = ?');
        $st->execute([$sn]);
        $p = $st->fetch() ?: null;
        if ($p) {
            $st = db()->prepare('SELECT file, UNIX_TIMESTAMP(updated_at) AS v FROM product_photos WHERE serial = ? AND position = 1');
            $st->execute([$sn]);
            $up = $st->fetch();
            $photo = $up ? $up['file'] . '?v=' . $up['v'] : default_photo($sn, 1);
            if ($p['photo_hidden'] || (!$up && !is_file(__DIR__ . '/' . default_photo($sn, 1)))) $photo = 'assets/img/photo-kit.jpg';
        }
    } catch (Throwable $e) {
        error_log('KIDAN 72 item preview: ' . $e->getMessage());
        $p = null;
    }
}

if ($p) {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $base = site_base_url();
    $title = $p['name'] . ' (' . $p['serial'] . ') — KIDAN 72';
    $desc = trim($p['kind'] === 'bag' ? ($p['summary'] ?: $p['short_text']) : $p['short_text']);
    if ($desc === '') $desc = 'Part of the KIDAN 72 Go-Bag, a Philippine typhoon and flood preparedness system. Always Ready.';
    $url = $base . 'item.html?sn=' . rawurlencode($p['serial']);

    $html = preg_replace('~<title>.*?</title>~s', '<title>' . $h($title) . '</title>', $html, 1);
    $html = preg_replace('~<meta name="description" content="[^"]*">~', '<meta name="description" content="' . $h($desc) . '">', $html, 1);
    $html = preg_replace('~<meta property="og:title" content="[^"]*">~', '<meta property="og:title" content="' . $h($title) . '">', $html, 1);
    $html = preg_replace('~<meta property="og:description" content="[^"]*">~', '<meta property="og:description" content="' . $h($desc) . '">', $html, 1);
    $html = str_replace('<meta property="og:type" content="website">',
        '<meta property="og:type" content="website">' . "\n" .
        '  <meta property="og:url" content="' . $h($url) . '">' . "\n" .
        '  <meta property="og:image" content="' . $h($base . $photo) . '">' . "\n" .
        '  <meta property="og:site_name" content="KIDAN 72">' . "\n" .
        '  <meta name="twitter:card" content="summary_large_image">' . "\n" .
        '  <link rel="canonical" href="' . $h($url) . '">', $html);
}

echo $html;
