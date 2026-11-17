<?php
/* KIDAN 72 — Admin: Readiness Check questions */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$modules = $pdo->query('SELECT code, name FROM modules ORDER BY sort, code')->fetchAll(PDO::FETCH_KEY_PAIR);

if (is_post()) {
    check_csrf();
    $codes = (array)($_POST['code'] ?? []);
    $rows = [];
    foreach ($codes as $i => $c) {
        $text = mb_substr(trim((string)($_POST['text'][$i] ?? '')), 0, 255);
        if ($text === '' || !empty($_POST['remove'][$i])) continue;
        $mod = (string)($_POST['module'][$i] ?? '');
        $rows[] = [$text, isset($modules[$mod]) ? $mod : null];
    }
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM readiness');
    $ins = $pdo->prepare('INSERT INTO readiness (code, text, module_code, sort) VALUES (?, ?, ?, ?)');
    foreach ($rows as $n => [$text, $mod]) $ins->execute([sprintf('RC-%02d', $n + 1), $text, $mod, ($n + 1) * 10]);
    $pdo->commit();
    log_action('Updated the Readiness Check questions (' . count($rows) . ' questions)');
    flash('Readiness questions saved.');
    redirect('readiness.php');
}

$list = $pdo->query('SELECT * FROM readiness ORDER BY sort, code')->fetchAll();
page_top('Readiness check', 'readiness.php', $me);
?>
<a class="back" href="more.php">← More</a>
<div class="page-head"><h1>Readiness check</h1><p class="lede">Yes/no questions in the "How ready are you?" quiz. When a visitor answers "no", the website suggests the training module you pick. Type in an empty box at the bottom to add a question.</p></div>
<form method="post" data-warn>
  <?= csrf_field() ?>
  <div class="list">
  <?php foreach (array_merge($list, array_fill(0, 2, ['code' => '', 'text' => '', 'module_code' => ''])) as $i => $r): $isNew = $r['code'] === ''; ?>
    <div class="row" style="display:grid;gap:8px">
      <input type="hidden" name="code[<?= $i ?>]" value="<?= h($r['code']) ?>">
      <label for="q<?= $i ?>" style="margin:0"><?= $isNew ? 'New question' : 'Question ' . ($i + 1) ?></label>
      <input type="text" id="q<?= $i ?>" name="text[<?= $i ?>]" value="<?= h($r['text']) ?>" placeholder="<?= $isNew ? 'e.g. Do you have a flashlight with spare batteries?' : '' ?>">
      <div class="row2 keep" style="align-items:center">
        <select name="module[<?= $i ?>]" aria-label="Suggested module for question <?= $i + 1 ?>">
          <option value="">Suggested module: none</option>
          <?php foreach ($modules as $c => $n): ?><option value="<?= h($c) ?>"<?= $r['module_code'] === $c ? ' selected' : '' ?>><?= h("$c · $n") ?></option><?php endforeach; ?>
        </select>
        <?php if (!$isNew): ?><label class="check" style="margin:0"><input type="checkbox" name="remove[<?= $i ?>]" value="1"> Remove</label><?php else: ?><span></span><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="sticky-save actions"><button class="btn primary" type="submit" data-busy="Saving…">Save questions</button></div>
</form>
<?php page_bottom();
