<?php
/* KIDAN 72 — robots.txt and sitemap.xml for search engines (Google).
   .htaccess sends /robots.txt and /sitemap.xml here, so the addresses
   are always right, also after moving to a new domain. */
require __DIR__ . '/lib.php';

$base = site_base_url();
if (($_GET['f'] ?? '') === 'robots') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /track.html\nDisallow: /order.html\n\nSitemap: {$base}sitemap.xml\n";
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
$urls = [['', '1.0'], ['gobag.html', '0.9'], ['system.html', '0.8'], ['training.html', '0.8'], ['readiness.html', '0.7'], ['about.html', '0.6']];
try {
    foreach (db()->query("SELECT serial FROM products ORDER BY kind = 'item', sort, serial")->fetchAll(PDO::FETCH_COLUMN) as $sn) {
        $urls[] = ['item.html?sn=' . rawurlencode($sn), '0.6'];
    }
} catch (Throwable $e) { /* pages only */ }
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$u, $prio]) {
    echo '  <url><loc>' . htmlspecialchars($base . $u, ENT_XML1) . '</loc><priority>' . $prio . "</priority></url>\n";
}
echo "</urlset>\n";
