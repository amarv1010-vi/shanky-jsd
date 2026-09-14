<?php
/**
 * Projects & Photos manager.
 * - Create/publish/delete projects shown on the public Projects page.
 * - Upload photos from phone or desktop; every image is auto-cropped
 *   server-side (GD) to the brand 3:2 ratio and resized to 1500x1000 JPEG.
 * - Files are numbered automatically: uploads/projects/proj-<projectId>-<seq>.jpg
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials/layout.php';
require_admin();

global $CONFIG;
$up = $CONFIG['uploads'];
$flash = '';
$flashKind = 'ok';

/** Centre-crop to 3:2 and resize to the configured output width. */
function crop_to_ratio(string $srcPath, string $destPath, array $up): bool
{
    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($srcPath),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
        default        => false,
    };
    if (!$src) return false;

    // auto-rotate JPEGs based on EXIF orientation (phone photos)
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($srcPath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $src = match ($orientation) {
            3       => imagerotate($src, 180, 0),
            6       => imagerotate($src, -90, 0),
            8       => imagerotate($src, 90, 0),
            default => $src,
        };
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $targetRatio = $up['ratio_w'] / $up['ratio_h']; // 1.5

    // largest centred window matching 3:2
    if ($w / $h > $targetRatio) {
        $cropH = $h;
        $cropW = (int) round($h * $targetRatio);
    } else {
        $cropW = $w;
        $cropH = (int) round($w / $targetRatio);
    }
    $x = (int) (($w - $cropW) / 2);
    $y = (int) (($h - $cropH) / 2);

    $outW = (int) $up['out_width'];
    $outH = (int) round($outW / $targetRatio);
    $dst = imagecreatetruecolor($outW, $outH);
    imagecopyresampled($dst, $src, 0, 0, $x, $y, $outW, $outH, $cropW, $cropH);

    $ok = imagejpeg($dst, $destPath, 86);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

// ---- actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? '';
        if ($title !== '' && in_array($category, ['highset', 'lowset', 'split', 'commercial'], true)) {
            db()->prepare(
                'INSERT INTO projects (title, category, location, description, is_published, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                mb_substr($title, 0, 190),
                $category,
                mb_substr(trim($_POST['location'] ?? ''), 0, 190),
                mb_substr(trim($_POST['description'] ?? ''), 0, 3000),
                isset($_POST['is_published']) ? 1 : 0,
                (int) ($_POST['sort_order'] ?? 0),
            ]);
            $flash = 'Project created. Now upload its photos below.';
        } else {
            $flash = 'Title and a valid category are required.';
            $flashKind = 'err';
        }

    } elseif ($action === 'upload' ) {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $exists = db()->prepare('SELECT id FROM projects WHERE id = ?');
        $exists->execute([$projectId]);
        if (!$exists->fetch()) {
            $flash = 'Choose a valid project first.';
            $flashKind = 'err';
        } elseif (empty($_FILES['photos']['name'][0])) {
            $flash = 'Select at least one photo to upload.';
            $flashKind = 'err';
        } else {
            if (!is_dir($up['dir'])) {
                mkdir($up['dir'], 0755, true);
            }
            $seqStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM project_images WHERE project_id = ?');
            $seqStmt->execute([$projectId]);
            $seq = (int) $seqStmt->fetchColumn();

            $saved = 0;
            $skipped = 0;
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { $skipped++; continue; }
                if (($_FILES['photos']['size'][$i] ?? 0) > $up['max_bytes']) { $skipped++; continue; }
                if (!is_uploaded_file($tmp)) { $skipped++; continue; }

                $mime = mime_content_type($tmp);
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { $skipped++; continue; }

                $seq++;
                $filename = sprintf('proj-%06d-%02d.jpg', $projectId, $seq);
                $dest = rtrim($up['dir'], '/') . '/' . $filename;

                if (crop_to_ratio($tmp, $dest, $up)) {
                    db()->prepare(
                        'INSERT INTO project_images (project_id, file_path, sort_order) VALUES (?, ?, ?)'
                    )->execute([$projectId, 'uploads/projects/' . $filename, $seq]);
                    $saved++;
                } else {
                    $seq--;
                    $skipped++;
                }
            }
            $flash = "{$saved} photo(s) uploaded, auto-cropped to 3:2 and published." . ($skipped ? " {$skipped} skipped (type/size/error)." : '');
            if (!$saved) $flashKind = 'err';
        }

    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE projects SET is_published = 1 - is_published WHERE id = ?')
            ->execute([(int) $_POST['id']]);
        $flash = 'Visibility updated.';

    } elseif ($action === 'delete_project') {
        $id = (int) $_POST['id'];
        $imgs = db()->prepare('SELECT file_path FROM project_images WHERE project_id = ?');
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll() as $img) {
            $f = JSD_ROOT . '/' . $img['file_path'];
            if (is_file($f)) @unlink($f);
        }
        db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]); // cascades to project_images
        $flash = 'Project and its photos deleted.';

    } elseif ($action === 'delete_image') {
        $id = (int) $_POST['id'];
        $img = db()->prepare('SELECT file_path FROM project_images WHERE id = ?');
        $img->execute([$id]);
        if ($row = $img->fetch()) {
            $f = JSD_ROOT . '/' . $row['file_path'];
            if (is_file($f)) @unlink($f);
            db()->prepare('DELETE FROM project_images WHERE id = ?')->execute([$id]);
            $flash = 'Photo deleted.';
        }
    }
}

$projects = db()->query('SELECT * FROM projects ORDER BY sort_order, created_at DESC')->fetchAll();
$imagesByProject = [];
foreach (db()->query('SELECT * FROM project_images ORDER BY sort_order')->fetchAll() as $img) {
    $imagesByProject[$img['project_id']][] = $img;
}

admin_header('Projects & Photos', 'projects');
?>
<h1>Projects &amp; Photos</h1>
<p class="sub">Create projects, then upload photos from your phone or computer.
Every photo is automatically straightened, centre-cropped to the brand <b>3:2 ratio</b> (1500×1000) and numbered
<code>proj-&lt;project&gt;-&lt;seq&gt;.jpg</code> — no editing needed on your side.</p>
<?php if ($flash): ?><div class="flash <?= $flashKind ?>"><?= e($flash) ?></div><?php endif; ?>

<form class="panel" method="post">
  <div class="section-title" style="margin-top:0">1 · Create a project</div>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="create">
  <div class="grid">
    <div><label>Title *</label><input name="title" required placeholder="e.g. Rochedale High-Set Residence"></div>
    <div><label>Category *</label>
      <select name="category" required>
        <option value="highset">High-Set House</option>
        <option value="lowset">Low-Set House</option>
        <option value="split">Split-Level Home</option>
        <option value="commercial">Low-Rise Commercial</option>
      </select>
    </div>
    <div><label>Location</label><input name="location" placeholder="e.g. Rochedale, QLD"></div>
    <div><label>Sort Order</label><input name="sort_order" type="number" value="0"></div>
    <div class="full"><label>Description</label><textarea name="description" placeholder="One or two sentences shown on the project card…"></textarea></div>
    <div class="full" style="display:flex;align-items:center;gap:10px">
      <input type="checkbox" name="is_published" id="pub" checked style="width:auto">
      <label for="pub" style="margin:0">Published (visible on the website)</label>
    </div>
  </div>
  <button class="btn btn-gold" type="submit" style="margin-top:18px">Create Project</button>
</form>

<form class="panel" method="post" enctype="multipart/form-data">
  <div class="section-title" style="margin-top:0">2 · Upload photos</div>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="upload">
  <div class="grid">
    <div>
      <label>Project *</label>
      <select name="project_id" required>
        <option value="">Select project…</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?> (<?= e($p['category']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Photos * (JPG / PNG / WebP, up to 10 MB each)</label>
      <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
      <p class="help">On mobile this opens your camera roll — select multiple photos at once.</p>
    </div>
  </div>
  <button class="btn btn-gold" type="submit" style="margin-top:18px">Upload &amp; Auto-Crop to 3:2</button>
</form>

<div class="section-title">All projects (<?= count($projects) ?>)</div>
<?php foreach ($projects as $p): $imgs = $imagesByProject[$p['id']] ?? []; ?>
  <div class="panel" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:center">
      <div>
        <b style="color:var(--gold-hi);font-size:1.05rem"><?= e($p['title']) ?></b>
        <span class="badge <?= $p['is_published'] ? 'won' : 'closed' ?>" style="margin-left:10px"><?= $p['is_published'] ? 'PUBLISHED' : 'HIDDEN' ?></span>
        <div class="help"><?= e($p['category']) ?> · <?= e($p['location'] ?: 'no location') ?> · <?= count($imgs) ?> photo(s)</div>
      </div>
      <div style="display:flex;gap:8px">
        <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-ghost btn-sm" type="submit"><?= $p['is_published'] ? 'Hide' : 'Publish' ?></button></form>
        <form method="post" onsubmit="return confirm('Delete this project AND all its photos?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete_project"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
      </div>
    </div>
    <?php if ($imgs): ?>
      <div class="proj-imgs" style="margin-top:16px">
        <?php foreach ($imgs as $img): ?>
          <div style="text-align:center">
            <img class="thumb" src="../<?= e($img['file_path']) ?>" alt="">
            <form method="post" onsubmit="return confirm('Delete this photo?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete_image"><input type="hidden" name="id" value="<?= (int) $img['id'] ?>">
              <button class="btn btn-danger btn-sm" style="margin-top:5px" type="submit">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php admin_footer(); ?>
