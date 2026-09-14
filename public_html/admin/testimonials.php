<?php
/** Testimonials manager — add/publish/delete client reviews shown on the home page. */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials/layout.php';
require_admin();

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $quote = trim($_POST['quote'] ?? '');
        if ($name !== '' && $quote !== '') {
            db()->prepare(
                'INSERT INTO testimonials (name, project, rating, quote, is_published, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                mb_substr($name, 0, 120),
                mb_substr(trim($_POST['project'] ?? ''), 0, 190),
                max(1, min(5, (int) ($_POST['rating'] ?? 5))),
                mb_substr($quote, 0, 2000),
                isset($_POST['is_published']) ? 1 : 0,
                (int) ($_POST['sort_order'] ?? 0),
            ]);
            $flash = 'Testimonial added.';
        }
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE testimonials SET is_published = 1 - is_published WHERE id = ?')
            ->execute([(int) $_POST['id']]);
        $flash = 'Visibility updated.';
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM testimonials WHERE id = ?')->execute([(int) $_POST['id']]);
        $flash = 'Testimonial deleted.';
    }
}

$rows = db()->query('SELECT * FROM testimonials ORDER BY sort_order, created_at DESC')->fetchAll();

admin_header('Testimonials', 'testimonials');
?>
<h1>Testimonials</h1>
<p class="sub">Client reviews displayed on the home page. Keep the best three to six published.</p>
<?php if ($flash): ?><div class="flash ok"><?= e($flash) ?></div><?php endif; ?>

<form class="panel" method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="create">
  <div class="grid">
    <div><label>Client Name *</label><input name="name" required placeholder="e.g. Priya & Daniel M."></div>
    <div><label>Project</label><input name="project" placeholder="e.g. High-Set Build, Rochedale"></div>
    <div><label>Rating (1–5)</label><input name="rating" type="number" min="1" max="5" value="5"></div>
    <div><label>Sort Order</label><input name="sort_order" type="number" value="0"></div>
    <div class="full"><label>Quote *</label><textarea name="quote" required placeholder="What the client said…"></textarea></div>
    <div class="full" style="display:flex;align-items:center;gap:10px">
      <input type="checkbox" name="is_published" id="pub" checked style="width:auto">
      <label for="pub" style="margin:0">Published</label>
    </div>
  </div>
  <button class="btn btn-gold" type="submit" style="margin-top:18px">Add Testimonial</button>
</form>

<div class="table-wrap">
<table class="data">
  <tr><th>Client</th><th>Project</th><th>Rating</th><th>Quote</th><th>Visible</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['name']) ?></td>
    <td><?= e($r['project'] ?: '—') ?></td>
    <td><?= str_repeat('★', (int) $r['rating']) ?></td>
    <td style="max-width:380px"><?= e(mb_strimwidth($r['quote'], 0, 240, '…')) ?></td>
    <td><span class="badge <?= $r['is_published'] ? 'won' : 'closed' ?>"><?= $r['is_published'] ? 'YES' : 'NO' ?></span></td>
    <td style="white-space:nowrap">
      <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <button class="btn btn-ghost btn-sm" type="submit"><?= $r['is_published'] ? 'Hide' : 'Show' ?></button></form>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete this testimonial?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6">No testimonials yet.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_footer(); ?>
