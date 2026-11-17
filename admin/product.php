<?php
/* KIDAN 72 — Admin: edit one bag or item */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$sn = strtoupper((string)($_GET['sn'] ?? ''));
$load = function () use ($pdo, $sn) {
    $st = $pdo->prepare('SELECT * FROM products WHERE serial = ?');
    $st->execute([$sn]);
    return $st->fetch();
};
$p = $load();
if (!$p) { flash('That product was not found.', 'error'); redirect('products.php'); }
$isBag = $p['kind'] === 'bag';
$self = 'product.php?sn=' . urlencode($sn);

$cats  = $pdo->query('SELECT code, name FROM categories ORDER BY sort, code')->fetchAll(PDO::FETCH_KEY_PAIR);
$icons = icon_names();

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? 'save';
    try {
        if ($action === 'save') {
            $t = fn(string $k, int $max = 5000) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
            $name = $t('name', 120);
            if ($name === '') throw new RuntimeException('The name cannot be empty.');
            $video = $t('video', 255);
            if (!youtube_ok($video)) throw new RuntimeException('The video must be a YouTube link (youtube.com/watch?v=…, youtu.be/…, or a Shorts link).');
            $priceRaw = str_replace([',', 'PHP', 'php', '₱', ' '], '', $t('price', 20));
            if ($priceRaw !== '' && (!is_numeric($priceRaw) || (float)$priceRaw < 0)) throw new RuntimeException('Price: numbers only, please.');
            $icon = in_array($t('icon', 30), $icons, true) ? $t('icon', 30) : $p['icon'];

            $fields = [
                'name' => $name, 'icon' => $icon, 'short_text' => $t('short'), 'video' => $video,
                // an Encoder does not see or change prices: keep the price as it is
                'price' => !sees_sales($me) ? $p['price'] : ($priceRaw === '' ? null : round((float)$priceRaw, 2)),
                'photos' => max(1, min(9, (int)($_POST['photos'] ?? 1))),
                'photo_hidden' => empty($_POST['photo_hidden']) ? 0 : 1,
                'sold_separately' => empty($_POST['sold_separately']) ? 0 : 1,
                'sold_out' => empty($_POST['sold_out']) ? 0 : 1,
                'choice' => null,
            ];
            // Choice (e.g. Black, Brown): the customer picks one when ordering
            $values = array_values(array_unique(array_filter(array_map(fn($v) => mb_substr(trim(str_replace('|', '', $v)), 0, 40), explode(',', $t('choice_values', 200))), fn($v) => $v !== '')));
            if ($values) {
                $old = product_choice($p['choice'] ?? null);
                $c = ['label' => $t('choice_label', 40) ?: 'Colour', 'values' => $values];
                if ($old && !empty($old['photos']) && $old['values'] === $values) $c['photos'] = $old['photos'];   // photo per colour stays
                $fields['choice'] = json_encode($c, JSON_UNESCAPED_UNICODE);
            }
            if ($isBag) {
                $specs = [];
                foreach ((array)($_POST['spec_label'] ?? []) as $i => $label) {
                    $label = trim((string)$label); $value = trim((string)($_POST['spec_value'][$i] ?? ''));
                    if ($label !== '' || $value !== '') $specs[] = [$label, $value];
                }
                $fields += ['label' => $t('label', 80), 'summary' => $t('summary'), 'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE)];
            } else {
                $cat = (string)($_POST['cat'] ?? '');
                if (!isset($cats[$cat])) throw new RuntimeException('Please choose a category.');
                $fields += [
                    'cat' => $cat, 'why' => $t('why'), 'spec' => $t('spec'), 'maintain' => $t('maintain'),
                    'steps' => json_encode(lines_to_list((string)($_POST['steps'] ?? '')), JSON_UNESCAPED_UNICODE),
                    'parts' => json_encode(lines_to_list((string)($_POST['parts'] ?? '')), JSON_UNESCAPED_UNICODE),
                ];
            }
            $pdo->beginTransaction();
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $pdo->prepare("UPDATE products SET $set WHERE serial = ?")->execute([...array_values($fields), $sn]);
            if ($isBag) {
                $pdo->prepare('DELETE FROM bag_contents WHERE bag_serial = ?')->execute([$sn]);
                $ins = $pdo->prepare('INSERT INTO bag_contents (bag_serial, child_serial, sort) VALUES (?, ?, ?)');
                $valid = $pdo->query('SELECT serial FROM products')->fetchAll(PDO::FETCH_COLUMN);
                $sort = 0;
                foreach (array_unique((array)($_POST['contents'] ?? [])) as $child) {
                    if ($child !== $sn && in_array($child, $valid, true)) $ins->execute([$sn, $child, $sort += 10]);
                }
            }
            $pdo->commit();
            $note = [];
            if (($p['price'] === null) !== ($fields['price'] === null) || (float)$p['price'] != (float)$fields['price']) $note[] = 'price ' . money($fields['price']);
            if ((int)$p['sold_out'] !== $fields['sold_out']) $note[] = $fields['sold_out'] ? 'marked sold out' : 'back in stock';
            if ($p['video'] !== $fields['video']) $note[] = 'video changed';
            log_action("Edited $sn ({$fields['name']})" . ($note ? ': ' . implode(', ', $note) : ''));
            flash('Saved. The website now shows the new details.');
        }

        if ($action === 'photo_upload') {
            $pos = max(1, min(9, (int)($_POST['position'] ?? 1)));
            $rel = save_uploaded_photo($_FILES['photo'] ?? [], 'products', $sn . ($pos > 1 ? '-' . $pos : ''));
            $pdo->prepare('INSERT INTO product_photos (serial, position, file) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE file = VALUES(file), updated_at = CURRENT_TIMESTAMP')->execute([$sn, $pos, $rel]);
            if ($pos > (int)$p['photos']) $pdo->prepare('UPDATE products SET photos = ? WHERE serial = ?')->execute([$pos, $sn]);
            log_action("Uploaded photo $pos for $sn ({$p['name']})");
            flash("Photo $pos uploaded.");
        }

        if ($action === 'photo_remove') {
            $pos = (int)($_POST['position'] ?? 0);
            $st = $pdo->prepare('SELECT file FROM product_photos WHERE serial = ? AND position = ?');
            $st->execute([$sn, $pos]);
            if ($file = $st->fetchColumn()) {
                @unlink(KIDAN_ROOT . '/' . $file);
                $pdo->prepare('DELETE FROM product_photos WHERE serial = ? AND position = ?')->execute([$sn, $pos]);
                log_action("Removed uploaded photo $pos of $sn ({$p['name']})");
                flash("Uploaded photo $pos removed.");
            }
        }

        if ($action === 'delete' && !$isBag && sees_sales($me)) {
            $st = $pdo->prepare('SELECT file FROM product_photos WHERE serial = ?');
            $st->execute([$sn]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $file) @unlink(KIDAN_ROOT . '/' . $file);
            $pdo->prepare('DELETE FROM products WHERE serial = ?')->execute([$sn]);
            log_action("Deleted item $sn ({$p['name']})");
            flash("$sn deleted from the website. Old orders that include it are kept.");
            redirect('products.php');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong: ' . $e->getMessage(), 'error');
    }
    redirect($self);
}

$st = $pdo->prepare('SELECT position, file, updated_at FROM product_photos WHERE serial = ?');
$st->execute([$sn]);
$uploads = [];
foreach ($st as $r) $uploads[(int)$r['position']] = $r;

$children = [];
if ($isBag) {
    $st = $pdo->prepare('SELECT child_serial FROM bag_contents WHERE bag_serial = ?');
    $st->execute([$sn]);
    $children = $st->fetchAll(PDO::FETCH_COLUMN);
}
$all = $pdo->query('SELECT serial, name, kind, cat FROM products ORDER BY kind = \'item\', cat, serial')->fetchAll();
$yt = youtube_id($p['video']);

page_top($p['name'], 'products.php', $me);
?>
<div class="actions" style="justify-content:space-between;margin-bottom:8px"><a class="back" href="products.php" style="margin:0">← Products</a><a href="../item.html?sn=<?= h(urlencode($sn)) ?>" target="_blank" rel="noopener">See it on the website ↗</a></div>
<h1><?= h($p['name']) ?> <span class="mono muted"><?= h($sn) ?></span></h1>

<form method="post" id="main" data-warn>
  <?= csrf_field() ?><input type="hidden" name="action" value="save">

  <div class="card">
    <h3>Basics</h3>
    <div class="row2">
      <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" value="<?= h($p['name']) ?>" required maxlength="120"></div>
      <?php if (sees_sales($me)): ?>
      <div class="field"><label for="price">Price in pesos <span class="hint">Empty = shows "Price to follow".</span></label>
        <input type="text" inputmode="decimal" id="price" name="price" value="<?= $p['price'] === null ? '' : h(rtrim(rtrim($p['price'], '0'), '.')) ?>" placeholder="xx" class="price-input"></div>
      <?php endif; ?>
    </div>
    <div class="row3">
      <?php if ($isBag): ?>
        <div class="field"><label for="label">Label <span class="hint">e.g. "Complete system"</span></label><input type="text" id="label" name="label" value="<?= h($p['label']) ?>"></div>
      <?php else: ?>
        <div class="field"><label for="cat">Category</label>
          <select id="cat" name="cat"><?php foreach ($cats as $code => $cname): ?><option value="<?= h($code) ?>"<?= $p['cat'] === $code ? ' selected' : '' ?>><?= h($cname) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="field"><label for="icon">Icon <span class="hint">Shown when there is no photo.</span></label>
        <select id="icon" name="icon"><?php foreach ($icons as $ic): ?><option<?= $p['icon'] === $ic ? ' selected' : '' ?>><?= h($ic) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>&nbsp;</label>
        <label class="check"><input type="checkbox" name="sold_separately" value="1"<?= $p['sold_separately'] ? ' checked' : '' ?>> Customers can order it on its own</label>
        <label class="check" style="margin-top:8px"><input type="checkbox" name="sold_out" value="1"<?= $p['sold_out'] ? ' checked' : '' ?>> Sold out <span class="hint" style="display:inline">(hides "Add to order")</span></label></div>
    </div>
    <?php $ch = product_choice($p['choice'] ?? null); ?>
    <div class="row2">
      <div class="field"><label for="choice_values">Customer chooses <span class="hint">e.g. "Black, Brown". Separate with commas. Empty = no choice.</span></label>
        <input type="text" id="choice_values" name="choice_values" value="<?= h($ch ? implode(', ', $ch['values']) : '') ?>" maxlength="200"></div>
      <div class="field"><label for="choice_label">Name of the choice <span class="hint">e.g. "Colour" or "Pouch colour"</span></label>
        <input type="text" id="choice_label" name="choice_label" value="<?= h($ch['label'] ?? '') ?>" maxlength="40" placeholder="Colour"></div>
    </div>
    <div class="field"><label for="short">Short description <span class="hint">One line, shown on the product cards.</span></label><textarea id="short" name="short" style="min-height:70px"><?= h($p['short_text']) ?></textarea></div>
    <?php if ($isBag): ?>
      <div class="field"><label for="summary">Summary <span class="hint">The longer text on the bag's own page.</span></label><textarea id="summary" name="summary"><?= h($p['summary']) ?></textarea></div>
    <?php endif; ?>
  </div>

  <?php if (!$isBag): ?>
  <div class="card">
    <h3>Video</h3>
    <div class="field"><label for="video">YouTube link <span class="hint">Paste any normal YouTube link. Empty = "Video coming soon".</span></label>
      <input type="url" id="video" name="video" value="<?= h($p['video']) ?>" placeholder="https://www.youtube.com/watch?v=…">
      <?php if ($yt): ?><div class="yt"><img src="https://i.ytimg.com/vi/<?= h($yt) ?>/mqdefault.jpg" alt=""><a href="<?= h($p['video']) ?>" target="_blank" rel="noopener">Check the video ↗</a></div><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>Item page text</h3>
    <div class="field"><label for="why">Why it's in the bag</label><textarea id="why" name="why"><?= h($p['why']) ?></textarea></div>
    <div class="field"><label for="steps">How to use it <span class="hint">One step per line.</span></label><textarea id="steps" name="steps" style="min-height:150px"><?= h(list_to_lines($p['steps'])) ?></textarea></div>
    <div class="row2">
      <div class="field"><label for="spec">Specification</label><textarea id="spec" name="spec" style="min-height:70px"><?= h($p['spec']) ?></textarea></div>
      <div class="field"><label for="maintain">Maintenance</label><textarea id="maintain" name="maintain" style="min-height:70px"><?= h($p['maintain']) ?></textarea></div>
    </div>
    <div class="field"><label for="parts">What's inside the kit <span class="hint">One part per line. Leave empty if it is not a kit.</span></label><textarea id="parts" name="parts"><?= h(list_to_lines($p['parts'])) ?></textarea></div>
  </div>
  <?php else: ?>
  <input type="hidden" name="video" value="<?= h($p['video']) ?>">
  <div class="card">
    <h3>Specifications table</h3>
    <p class="hint">Left: label (e.g. "Coverage"). Right: value (e.g. "1 person · 72 hours"). Empty rows are ignored.</p>
    <?php $specs = json_list($p['specs']); for ($i = 0; $i < count($specs) + 3; $i++): $row = $specs[$i] ?? ['', '']; ?>
      <div class="spec-row">
        <input type="text" name="spec_label[]" value="<?= h($row[0] ?? '') ?>" aria-label="Label <?= $i + 1 ?>">
        <input type="text" name="spec_value[]" value="<?= h($row[1] ?? '') ?>" aria-label="Value <?= $i + 1 ?>">
      </div>
    <?php endfor; ?>
  </div>
  <div class="card">
    <h3>What's inside this bag</h3>
    <p class="hint">Tick everything packed in it. <?= $sn === 'K72-BAG-000' ? 'The Preparedness Bag holds every item and 1 KIDAN Pouch.' : '' ?></p>
    <div class="checks">
      <?php foreach ($all as $a): if ($a['serial'] === $sn) continue; ?>
        <label class="check"><input type="checkbox" name="contents[]" value="<?= h($a['serial']) ?>"<?= in_array($a['serial'], $children, true) ? ' checked' : '' ?>>
          <span><?= h($a['name']) ?> <span class="mono"><?= h($a['serial']) ?></span></span></label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <h3>Photo settings</h3>
    <div class="row2">
      <div class="field"><label for="photos">Number of photos</label>
        <select id="photos" name="photos"><?php for ($n = 1; $n <= 6; $n++): ?><option<?= (int)$p['photos'] === $n ? ' selected' : '' ?>><?= $n ?></option><?php endfor; ?></select></div>
      <div class="field"><label>&nbsp;</label>
        <label class="check"><input type="checkbox" name="photo_hidden" value="1"<?= $p['photo_hidden'] ? ' checked' : '' ?>> Hide the photo for now (shows "Photo coming soon")</label></div>
    </div>
  </div>

  <div class="sticky-save actions"><button class="btn primary" type="submit" data-busy="Saving…">Save changes</button><a class="btn ghost" href="products.php">Cancel</a></div>
</form>

<h2>Photos</h2>
<p class="muted">Upload a JPG or PNG straight from your phone or computer. It is resized automatically. Landscape (4:3) looks best.</p>
<div class="photos">
  <?php for ($n = 1; $n <= max(1, (int)$p['photos']) + 1; $n++): $extra = $n > (int)$p['photos']; ?>
    <div class="photo-card">
      <div class="pic"><?php if ($extra): ?><span>Add photo <?= $n ?></span><?php else: ?><img src="<?= h(current_photo($sn, $n, $uploads)) ?>" alt="Photo <?= $n ?>" onerror="this.outerHTML='<span>No photo yet</span>'"><?php endif; ?></div>
      <b><?= $n === 1 ? 'Main photo' : 'Photo ' . $n ?></b>
      <?php if (!$extra): ?><span class="hint"><?= isset($uploads[$n]) ? 'Uploaded in admin' : 'From the website folder' ?></span><?php endif; ?>
      <form method="post" enctype="multipart/form-data" style="margin-top:8px">
        <?= csrf_field() ?><input type="hidden" name="action" value="photo_upload"><input type="hidden" name="position" value="<?= $n ?>">
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-label="Choose photo <?= $n ?>">
        <button class="btn small" type="submit"><?= $extra ? 'Add' : 'Replace' ?></button>
      </form>
      <?php if (isset($uploads[$n])): ?>
        <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="action" value="photo_remove"><input type="hidden" name="position" value="<?= $n ?>">
          <button class="linklike" type="submit">Remove upload (go back to the original)</button></form>
      <?php endif; ?>
    </div>
  <?php endfor; ?>
</div>

<?php if (!$isBag && sees_sales($me)): ?>
<form method="post" onsubmit="return confirm('Delete <?= h($p['name']) ?> from the website? This cannot be undone.')" style="margin-top:40px">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete">
  <button class="btn danger small" type="submit">Delete this item</button>
</form>
<?php endif; ?>
<?php page_bottom();
