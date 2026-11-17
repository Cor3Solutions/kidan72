<?php
/* KIDAN 72 — Small charts for the admin pages, drawn as SVG on the server
   (no extra files, fast on phones, and they print well).

   chart_columns($points, $opt)   bars over time: [[label, value, tooltip], ...]
   chart_hbars($rows, $opt)       ranked bars:    [[label, value, note], ...]
   chart_donut($rows)             shares:         [[label, value, color, note], ...]
   chart_spark($values)           a tiny trend line for a number tile
   peso_short(12500)              "₱12.5k" */

if (!defined('KIDAN_ROOT')) exit;

const CHART_ORANGE = '#E2632A';
const CHART_COLORS = ['#E2632A', '#17140F', '#C59F78', '#3F7A4A', '#5E5A55', '#D9A441', '#8FB3C9', '#A8411A'];

function peso_short(float $v): string {
    $a = abs($v);
    $trim = fn(string $n) => str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : $n;   // "12.0" → "12", "290" stays "290"
    if ($a >= 1000000) return '₱' . $trim(number_format($v / 1000000, 1)) . 'M';
    if ($a >= 1000) return '₱' . $trim(number_format($v / 1000, $a >= 100000 ? 0 : 1)) . 'k';
    return '₱' . number_format($v, 0);
}

/* A "nice" top for the axis: 0, 5k, 10k ... */
function chart_nice_max(float $max): float {
    if ($max <= 0) return 1;
    $p = 10 ** floor(log10($max));
    foreach ([1, 2, 2.5, 5, 10] as $m) if ($max <= $m * $p) return $m * $p;
    return 10 * $p;
}

/* Two drawings: a wide one for computers and a compact one for phones
   (text stays readable at both sizes; CSS shows the right one). */
function chart_columns(array $points, array $opt = []): string {
    if (!$points) return '<p class="chart-empty">No sales in this period.</p>';
    return chart_columns_svg($points, $opt + ['w' => 720, 'h' => 260, 'cls' => 'wide'])
         . chart_columns_svg($points, $opt + ['w' => 360, 'h' => 240, 'cls' => 'narrow']);
}

function chart_columns_svg(array $points, array $opt): string {
    $n = count($points);
    $W = $opt['w']; $H = $opt['h']; $L = $W < 500 ? 44 : 52; $R = 8; $T = 14; $B = 30;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $max = chart_nice_max(max(array_map(fn($p) => (float)$p[1], $points)));
    $out = '<svg class="chart cols ' . $opt['cls'] . '" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . h($opt['label'] ?? 'Chart') . '">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $T + $ph - $ph * $i / 4;
        $out .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" class="grid"/>'
              . '<text x="' . ($L - 8) . '" y="' . round($y + 4, 1) . '" class="ax" text-anchor="end">' . h(peso_short($max * $i / 4)) . '</text>';
    }
    $slot = $pw / $n;
    $bw = max(2, min(46, $slot * 0.68));
    $every = $W < 500 ? ($n <= 7 ? 1 : ($n <= 14 ? 2 : ($n <= 24 ? 4 : 5))) : ($n <= 12 ? 1 : ($n <= 16 ? 2 : 5));
    $best = max(array_map(fn($p) => (float)$p[1], $points));
    foreach (array_values($points) as $i => [$lab, $val, $tip]) {
        $h = $max ? $ph * (float)$val / $max : 0;
        $x = $L + $slot * $i + ($slot - $bw) / 2;
        $cls = (float)$val > 0 && (float)$val === $best ? 'col top' : 'col';
        $out .= '<g><title>' . h($tip) . '</title>'
              . '<rect x="' . round($x, 1) . '" y="' . round($T + $ph - $h, 1) . '" width="' . round($bw, 1) . '" height="' . round(max($h, (float)$val > 0 ? 1.5 : 0), 1) . '" rx="2" class="' . $cls . '"/>'
              . '<rect x="' . round($L + $slot * $i, 1) . '" y="' . $T . '" width="' . round($slot, 1) . '" height="' . $ph . '" class="hit"/></g>';
        if ($i % $every === 0 || $i === $n - 1 && $n <= 31 && ($n - 1) % $every > $every / 2) {
            $out .= '<text x="' . round($L + $slot * ($i + .5), 1) . '" y="' . ($H - 10) . '" class="ax" text-anchor="middle">' . h($lab) . '</text>';
        }
    }
    return $out . '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . ($T + $ph) . '" y2="' . ($T + $ph) . '" class="base"/></svg>';
}

function chart_hbars(array $rows, array $opt = []): string {
    if (!$rows) return '<p class="chart-empty">Nothing in this period.</p>';
    $max = max(array_map(fn($r) => (float)$r[1], $rows)) ?: 1;
    $fmt = $opt['format'] ?? fn($v) => money(round((float)$v));
    $out = '<ol class="hbars">';
    foreach ($rows as $i => [$label, $val, $note]) {
        $out .= '<li><div class="hb-top"><span class="hb-label">' . h($label) . '</span><b>' . h($fmt($val)) . '</b></div>'
              . '<div class="hb-track"><i style="width:' . max(1.5, round((float)$val / $max * 100, 1)) . '%' . ($i === 0 ? '' : ';opacity:' . max(.45, 1 - $i * .07)) . '"></i></div>'
              . ($note !== '' ? '<small>' . h($note) . '</small>' : '') . '</li>';
    }
    return $out . '</ol>';
}

function chart_donut(array $rows, string $center = '', string $centerSub = ''): string {
    $total = array_sum(array_map(fn($r) => (float)$r[1], $rows));
    if ($total <= 0) return '<p class="chart-empty">Nothing in this period.</p>';
    $r = 52; $c = 2 * M_PI * $r; $off = 0;
    $svg = '<svg class="chart donut" viewBox="0 0 140 140" role="img" aria-hidden="true"><circle cx="70" cy="70" r="' . $r . '" class="ring"/>';
    foreach ($rows as [$label, $val, $color]) {
        $len = $c * (float)$val / $total;
        if ($len <= 0) continue;
        $svg .= '<circle cx="70" cy="70" r="' . $r . '" stroke="' . h($color) . '" stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 70 70)"><title>' . h($label . ': ' . round((float)$val / $total * 100) . '%') . '</title></circle>';
        $off += $len;
    }
    $svg .= '<text x="70" y="68" text-anchor="middle" class="dc">' . h($center) . '</text><text x="70" y="86" text-anchor="middle" class="ds">' . h($centerSub) . '</text></svg>';
    $legend = '<ul class="legend">';
    foreach ($rows as [$label, $val, $color, $note]) {
        $legend .= '<li><i style="background:' . h($color) . '"></i><span>' . h($label) . '</span><b>' . round((float)$val / $total * 100) . '%</b>' . ($note !== '' ? '<small>' . h($note) . '</small>' : '') . '</li>';
    }
    return '<div class="donut-wrap">' . $svg . $legend . '</ul></div>';
}

function chart_spark(array $values): string {
    $n = count($values);
    if ($n < 2) return '';
    $max = max($values) ?: 1;
    $pts = [];
    foreach (array_values($values) as $i => $v) $pts[] = round($i / ($n - 1) * 100, 1) . ',' . round(28 - (float)$v / $max * 26, 1);
    return '<svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><polyline points="0,30 ' . implode(' ', $pts) . ' 100,30" class="area"/><polyline points="' . implode(' ', $pts) . '" class="line"/></svg>';
}
