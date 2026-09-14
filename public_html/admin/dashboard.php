<?php
/** Admin dashboard: headline counts + latest enquiries at a glance. */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials/layout.php';
require_admin();

$counts = [];
foreach (['enquiries', 'contact_messages', 'estimates', 'projects', 'testimonials'] as $t) {
    $counts[$t] = (int) db()->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
}
$newEnquiries = (int) db()->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
$latest = db()->query('SELECT * FROM enquiries ORDER BY created_at DESC LIMIT 6')->fetchAll();

admin_header('Dashboard', 'dashboard');
?>
<h1>Dashboard</h1>
<p class="sub">Welcome back. Here's what's happening across jsdconstruction.com.au.</p>

<div class="cards">
  <div class="card"><b><?= $newEnquiries ?></b><span>New Enquiries</span></div>
  <div class="card"><b><?= $counts['enquiries'] ?></b><span>Total Enquiries</span></div>
  <div class="card"><b><?= $counts['estimates'] ?></b><span>Estimator Leads</span></div>
  <div class="card"><b><?= $counts['contact_messages'] ?></b><span>Contact Messages</span></div>
  <div class="card"><b><?= $counts['projects'] ?></b><span>Projects Live</span></div>
</div>

<div class="section-title">Latest enquiries</div>
<div class="table-wrap">
<table class="data">
  <tr><th>#</th><th>Received</th><th>Name</th><th>Mobile</th><th>Purpose</th><th>Area</th><th>Status</th></tr>
  <?php foreach ($latest as $r): ?>
  <tr>
    <td>JSD-<?= str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT) ?></td>
    <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
    <td><?= e($r['name']) ?></td>
    <td><a href="tel:<?= e($r['mobile']) ?>"><?= e($r['mobile']) ?></a></td>
    <td><?= e($r['purpose']) ?></td>
    <td><?= e($r['area']) ?></td>
    <td><span class="badge <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$latest): ?><tr><td colspan="7">No enquiries yet.</td></tr><?php endif; ?>
</table>
</div>
<p class="help" style="margin-top:14px"><a href="enquiries.php">Open the full enquiries database →</a></p>
<?php admin_footer(); ?>
