<?php
/* KIDAN 72 — Admin: training modules (list + edit one) */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$code = strtoupper((string)($_GET['code'] ?? ''));
$icons = icon_names();

if ($code !== '') {
    $st = $pdo->prepare('SELECT * FROM modules WHERE code = ?');
    $st->execute([$code]);
    $m = $st->fetch();
    if (!$m) { flash('That module was not found.', 'error'); redirect('training.php'); }

    if (is_post()) {
        check_csrf();
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $video = trim((string)($_POST['video'] ?? ''));
        if ($name === '') flash('The name cannot be empty.', 'error');
        elseif (!youtube_ok($video)) flash('The video must be a YouTube link.', 'error');
        else {
            $icon = in_array($_POST['icon'] ?? '', $icons, true) ? $_POST['icon'] : $m['icon'];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE modules SET name = ?, icon = ?, short_text = ?, lessons = ?, video = ? WHERE code = ?')->execute([
                $name, $icon, mb_substr(trim((string)($_POST['short'] ?? '')), 0, 1000),
                json_encode(lines_to_list((string)($_POST['lessons'] ?? '')), JSON_UNESCAPED_UNICODE), mb_substr($video, 0, 255), $code]);
            $pdo->prepare('DELETE FROM module_items WHERE module_code = ?')->execute([$code]);
            $ins = $pdo->prepare('INSERT INTO module_items (module_code, serial, sort) VALUES (?, ?, ?)');
            $valid = $pdo->query('SELECT serial FROM products')->fetchAll(PDO::FETCH_COLUMN);
            $sort = 0;
            foreach (array_unique((array)($_POST['items'] ?? [])) as $s) if (in_array($s, $valid, true)) $ins->execute([$code, $s, $sort += 10]);
            $pdo->commit();
            log_action("Edited training module $code ($name)");
            flash("Module $code saved.");
        }
        redirect('training.php?code=' . urlencode($code));
    }

    $st = $pdo->prepare('SELECT serial FROM module_items WHERE module_code = ?');
    $st->execute([$code]);
    $chosen = $st->fetchAll(PDO::FETCH_COLUMN);
    $all = $pdo->query('SELECT serial, name FROM products ORDER BY kind = \'item\', cat, serial')->fetchAll();
    $yt = youtube_id($m['video']);

    page_top('Module ' . $code, 'training.php', $me);
    ?>
    <div class="actions" style="justify-content:space-between;margin-bottom:8px"><a class="back" href="training.php" style="margin:0">← Training modules</a><a href="../training.html" target="_blank" rel="noopener">See it on the website ↗</a></div>
    <h1>Module <?= h($code) ?> · <?= h($m['name']) ?></h1>
    <form method="post" data-warn>
      <?= csrf_field() ?>
      <div class="card">
        <div class="row2">
          <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" value="<?= h($m['name']) ?>" required></div>
          <div class="field"><label for="icon">Icon</label><select id="icon" name="icon"><?php foreach ($icons as $ic): ?><option<?= $m['icon'] === $ic ? ' selected' : '' ?>><?= h($ic) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field"><label for="short">Short description</label><textarea id="short" name="short" style="min-height:70px"><?= h($m['short_text']) ?></textarea></div>
        <div class="field"><label for="video">YouTube link <span class="hint">Empty = "Video coming soon".</span></label>
          <input type="url" id="video" name="video" value="<?= h($m['video']) ?>" placeholder="https://www.youtube.com/watch?v=…">
          <?php if ($yt): ?><div class="yt"><img src="https://i.ytimg.com/vi/<?= h($yt) ?>/mqdefault.jpg" alt=""><a href="<?= h($m['video']) ?>" target="_blank" rel="noopener">Check the video ↗</a></div><?php endif; ?>
        </div>
        <div class="field"><label for="lessons">Lessons <span class="hint">One per line.</span></label><textarea id="lessons" name="lessons" style="min-height:160px"><?= h(list_to_lines($m['lessons'])) ?></textarea></div>
      </div>
      <div class="card">
        <h3>Kit items covered in this module</h3>
        <div class="checks">
          <?php foreach ($all as $a): ?>
            <label class="check"><input type="checkbox" name="items[]" value="<?= h($a['serial']) ?>"<?= in_array($a['serial'], $chosen, true) ? ' checked' : '' ?>>
              <span><?= h($a['name']) ?> <span class="mono"><?= h($a['serial']) ?></span></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="sticky-save actions"><button class="btn primary" type="submit" data-busy="Saving…">Save module</button><a class="btn ghost" href="training.php">Cancel</a></div>
    </form>
    <?php
    page_bottom();
    exit;
}

$modules = $pdo->query('SELECT m.*, (SELECT COUNT(*) FROM module_items i WHERE i.module_code = m.code) AS n FROM modules m ORDER BY sort, code')->fetchAll();
page_top('Training modules', 'training.php', $me);
?>
<a class="back" href="more.php">← More</a>
<div class="page-head"><h1>Training modules</h1><p class="lede">Tap a module to change its text, lessons, video or the kit items it covers.</p></div>
<div class="list">
  <?php foreach ($modules as $m): $lessons = count(json_list($m['lessons'])); ?>
    <a class="row" href="?code=<?= h(urlencode($m['code'])) ?>">
      <span class="row-main"><span class="row-title"><?= h($m['code']) ?> · <?= h($m['name']) ?></span><span class="row-sub"><?= h($m['short_text']) ?></span></span>
      <span class="row-side"><span class="row-sub"><?= $lessons ?> lesson<?= $lessons === 1 ? '' : 's' ?> · <?= (int)$m['n'] ?> item<?= (int)$m['n'] === 1 ? '' : 's' ?></span><?= $m['video'] !== '' ? '<span class="badge repeat">video</span>' : '<span class="badge">no video yet</span>' ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php page_bottom();
