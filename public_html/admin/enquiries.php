<?php
/**
 * Enquiries database: full structured table of every submission
 * (enquiry form, contact form, estimator leads) with status updates,
 * delete, and CSV export.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials/layout.php';
require_admin();

$flash = '';

// ---- actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $table = $_POST['table'] ?? '';
    $allowed = ['enquiries', 'contact_messages', 'estimates'];
    if (in_array($table, $allowed, true) && $id > 0) {
        if (($_POST['action'] ?? '') === 'status') {
            $statusMap = [
                'enquiries'        => ['new', 'contacted', 'quoted', 'won', 'closed'],
                'contact_messages' => ['new', 'replied', 'closed'],
                'estimates'        => ['new', 'quoted', 'won', 'closed'],
            ];
            $status = $_POST['status'] ?? '';
            if (in_array($status, $statusMap[$table], true)) {
                db()->prepare("UPDATE {$table} SET status = ? WHERE id = ?")->execute([$status, $id]);
                $flash = 'Status updated.';
            }
        } elseif (($_POST['action'] ?? '') === 'delete') {
            db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
            $flash = 'Record deleted.';
        }
    }
}

// ---- CSV export (works on mobile & desktop — downloads a .csv file) ----
if (isset($_GET['export'])) {
    $table = $_GET['export'];
    if ($table === 'customers') {
        Users::ensureTable();
        $rows = db()->query("SELECT id, name, email, mobile, created_at FROM users WHERE role = 'customer' ORDER BY created_at DESC")->fetchAll();
    } elseif (in_array($table, ['enquiries', 'contact_messages', 'estimates'], true)) {
        $rows = db()->query("SELECT * FROM {$table} ORDER BY created_at DESC")->fetchAll();
    } else {
        $rows = null;
    }
    if ($rows !== null) {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=jsd-{$table}-" . date('Ymd') . '.csv');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly
        if ($rows) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $r) fputcsv($out, $r);
        }
        fclose($out);
        exit;
    }
}

Users::ensureTable();
$enquiries = db()->query('SELECT * FROM enquiries ORDER BY created_at DESC LIMIT 500')->fetchAll();
$messages  = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 500')->fetchAll();
$leads     = db()->query('SELECT * FROM estimates ORDER BY created_at DESC LIMIT 500')->fetchAll();
$customers = db()->query("SELECT id, name, email, mobile, created_at FROM users WHERE role = 'customer' ORDER BY created_at DESC LIMIT 500")->fetchAll();

admin_header('Enquiries', 'enquiries');

function status_form(string $table, array $row, array $options): void
{
    echo '<form method="post" style="display:flex;gap:6px;align-items:center">'
       . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
       . '<input type="hidden" name="table" value="' . e($table) . '">'
       . '<input type="hidden" name="id" value="' . (int) $row['id'] . '">'
       . '<input type="hidden" name="action" value="status">'
       . '<select name="status" onchange="this.form.submit()" style="width:auto;padding:6px 10px;font-size:.8rem">';
    foreach ($options as $o) {
        $sel = $o === $row['status'] ? ' selected' : '';
        echo "<option{$sel}>" . e($o) . '</option>';
    }
    echo '</select></form>';
}

function delete_form(string $table, int $id): void
{
    echo '<form method="post" onsubmit="return confirm(\'Delete this record permanently?\')" style="display:inline">'
       . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
       . '<input type="hidden" name="table" value="' . e($table) . '">'
       . '<input type="hidden" name="id" value="' . $id . '">'
       . '<input type="hidden" name="action" value="delete">'
       . '<button class="btn btn-danger btn-sm" type="submit">Delete</button></form>';
}
?>
<h1>Enquiries Database</h1>
<p class="sub">Every submission from the website, newest first. Export any table as CSV for Excel.</p>
<?php if ($flash): ?><div class="flash ok"><?= e($flash) ?></div><?php endif; ?>

<div class="section-title">Enquiry form submissions (<?= count($enquiries) ?>)
  <a class="btn btn-ghost btn-sm" style="margin-left:12px" href="?export=enquiries">Export CSV</a></div>
<div class="table-wrap">
<table class="data">
  <tr><th>#</th><th>Received</th><th>Name</th><th>Mobile</th><th>Email</th><th>Purpose</th><th>Area</th><th>Alt. No.</th><th>Enquiry</th><th>Status</th><th></th></tr>
  <?php foreach ($enquiries as $r): ?>
  <tr>
    <td>JSD-<?= str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT) ?></td>
    <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
    <td><?= e($r['name']) ?></td>
    <td><a href="tel:<?= e($r['mobile']) ?>"><?= e($r['mobile']) ?></a></td>
    <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
    <td><?= e($r['purpose']) ?></td>
    <td><?= e($r['area']) ?></td>
    <td><?= e($r['contact_number'] ?: '—') ?></td>
    <td style="max-width:280px"><?= nl2br(e(mb_strimwidth($r['message'], 0, 400, '…'))) ?></td>
    <td><?php status_form('enquiries', $r, ['new', 'contacted', 'quoted', 'won', 'closed']); ?></td>
    <td><?php delete_form('enquiries', (int) $r['id']); ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$enquiries): ?><tr><td colspan="11">No enquiries yet — they'll appear here the moment a customer submits the Enquiry form.</td></tr><?php endif; ?>
</table>
</div>

<div class="section-title">Contact messages (<?= count($messages) ?>)
  <a class="btn btn-ghost btn-sm" style="margin-left:12px" href="?export=contact_messages">Export CSV</a></div>
<div class="table-wrap">
<table class="data">
  <tr><th>#</th><th>Received</th><th>Name</th><th>Email</th><th>Phone</th><th>Topic</th><th>Routed To</th><th>Message</th><th>Status</th><th></th></tr>
  <?php foreach ($messages as $r): ?>
  <tr>
    <td><?= (int) $r['id'] ?></td>
    <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
    <td><?= e($r['name']) ?></td>
    <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
    <td><?= e($r['mobile']) ?></td>
    <td><?= e($r['topic']) ?></td>
    <td><?= e($r['routed_to']) ?></td>
    <td style="max-width:280px"><?= nl2br(e(mb_strimwidth($r['message'], 0, 400, '…'))) ?></td>
    <td><?php status_form('contact_messages', $r, ['new', 'replied', 'closed']); ?></td>
    <td><?php delete_form('contact_messages', (int) $r['id']); ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$messages): ?><tr><td colspan="10">No contact messages yet.</td></tr><?php endif; ?>
</table>
</div>

<div class="section-title">Estimator leads (<?= count($leads) ?>)
  <a class="btn btn-ghost btn-sm" style="margin-left:12px" href="?export=estimates">Export CSV</a></div>
<div class="table-wrap">
<table class="data">
  <tr><th>#</th><th>Received</th><th>Name</th><th>Email</th><th>Mobile</th><th>Type</th><th>Spec</th><th>Area m²</th><th>Slope</th><th>Range Shown</th><th>Status</th><th></th></tr>
  <?php foreach ($leads as $r): ?>
  <tr>
    <td><?= (int) $r['id'] ?></td>
    <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
    <td><?= e($r['name']) ?></td>
    <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
    <td><?= e($r['mobile']) ?></td>
    <td><?= e($r['build_type']) ?></td>
    <td><?= e($r['spec_level']) ?></td>
    <td><?= e((string) $r['floor_area']) ?></td>
    <td><?= e($r['slope']) ?></td>
    <td><?= e($r['estimate_range'] ?: '—') ?></td>
    <td><?php status_form('estimates', $r, ['new', 'quoted', 'won', 'closed']); ?></td>
    <td><?php delete_form('estimates', (int) $r['id']); ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$leads): ?><tr><td colspan="12">No estimator leads yet.</td></tr><?php endif; ?>
</table>
</div>

<div class="section-title">Registered customers (<?= count($customers) ?>)
  <a class="btn btn-ghost btn-sm" style="margin-left:12px" href="?export=customers">Export CSV</a></div>
<p class="help" style="margin:-6px 0 14px">Customers who created an account via the website Login → Create Account. Download as CSV for your records (opens in Excel / Google Sheets on mobile or desktop).</p>
<div class="table-wrap">
<table class="data">
  <tr><th>#</th><th>Joined</th><th>Name</th><th>Email (username)</th><th>Mobile</th></tr>
  <?php foreach ($customers as $r): ?>
  <tr>
    <td><?= (int) $r['id'] ?></td>
    <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
    <td><?= e($r['name']) ?></td>
    <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
    <td><?= e($r['mobile'] ?: '—') ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$customers): ?><tr><td colspan="5">No customer accounts yet — they'll appear here when visitors sign up via the Login page.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_footer(); ?>
