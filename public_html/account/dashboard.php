<?php
/**
 * Customer dashboard: enquiry tracking (matched by account email/mobile),
 * app download promo, support contacts, and change-password.
 */

require_once __DIR__ . '/auth.php';
$me = require_customer();

$flash = '';
$flashKind = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    portal_verify_csrf();
    $err = Users::changePassword((int) $me['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''));
    if ($err === null) {
        $flash = 'Password updated successfully.';
    } else {
        $flash = $err;
        $flashKind = 'err';
    }
}

// this customer's activity, matched by email
$enqStmt = db()->prepare('SELECT * FROM enquiries WHERE email = ? ORDER BY created_at DESC LIMIT 20');
$enqStmt->execute([$me['email']]);
$myEnquiries = $enqStmt->fetchAll();

$estStmt = db()->prepare('SELECT * FROM estimates WHERE email = ? ORDER BY created_at DESC LIMIT 20');
$estStmt->execute([$me['email']]);
$myEstimates = $estStmt->fetchAll();

$STATUS_HELP = [
    'new' => 'Received — our team is reviewing it',
    'contacted' => 'In progress — we\'ve been in touch',
    'quoted' => 'Quote prepared',
    'won' => 'Project underway 🎉',
    'closed' => 'Closed',
];
?>
<!DOCTYPE html>
<html lang="en-AU">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>My Account — JSD Construction</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logos/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <main>
    <section class="page-hero" style="padding-bottom:36px">
      <div class="hero-bg"></div>
      <div class="container" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:18px">
        <div>
          <span class="eyebrow">Client Portal</span>
          <h1 class="display-lg">G'day, <span class="gold-text"><?= pe($me['name'] ?: 'there') ?></span></h1>
          <p class="lede">Track your enquiries and quotes below. Full project tracking is coming to the JSD Customer App.</p>
        </div>
        <div style="display:flex;gap:12px">
          <a class="btn btn-ghost" href="../index.html">Website</a>
          <a class="btn btn-ghost" href="logout.php">Log Out</a>
        </div>
      </div>
    </section>

    <section class="section" style="padding-top:20px">
      <div class="container">
        <?php if (isset($_GET['welcome'])): ?>
          <div class="form-status ok" style="display:block;margin-bottom:30px">Welcome to JSD Construction! Your account is ready — any enquiry you submit with <b><?= pe($me['email']) ?></b> will appear here automatically.</div>
        <?php endif; ?>
        <?php if ($flash): ?>
          <div class="form-status <?= $flashKind ?>" style="display:block;margin-bottom:30px"><?= pe($flash) ?></div>
        <?php endif; ?>

        <h2 class="display-md" style="margin-bottom:22px">My <span class="gold-text">enquiries</span></h2>
        <?php if (!$myEnquiries): ?>
          <p class="lede">No enquiries linked to <?= pe($me['email']) ?> yet.
            <a href="../enquiry.html" style="color:var(--gold-hi)">Make your first enquiry →</a></p>
        <?php else: foreach ($myEnquiries as $r): ?>
          <div class="contact-card">
            <div style="width:100%">
              <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <b>#JSD-<?= str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT) ?> · <?= pe($r['purpose']) ?></b>
                <span class="project-tag" style="margin:0"><?= pe($STATUS_HELP[$r['status']] ?? $r['status']) ?></span>
              </div>
              <p style="color:var(--text-dim);font-size:.9rem;margin-top:6px">
                <?= pe($r['area']) ?> · submitted <?= pe(date('d M Y', strtotime($r['created_at']))) ?></p>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <?php if ($myEstimates): ?>
          <h2 class="display-md" style="margin:44px 0 22px">My <span class="gold-text">estimates</span></h2>
          <?php foreach ($myEstimates as $r): ?>
            <div class="contact-card">
              <div style="width:100%">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                  <b><?= pe(ucfirst($r['build_type'])) ?> · <?= pe((string) $r['floor_area']) ?> m² · <?= pe($r['spec_level']) ?></b>
                  <span class="project-tag" style="margin:0"><?= pe($r['estimate_range'] ?: 'range pending') ?></span>
                </div>
                <p style="color:var(--text-dim);font-size:.9rem;margin-top:6px">submitted <?= pe(date('d M Y', strtotime($r['created_at']))) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="grid-2 mt-4">
          <div class="cta-band" style="text-align:left">
            <span class="eyebrow">Coming Soon</span>
            <h2 class="display-md" style="margin:14px 0 12px">The JSD <span class="gold-text">Customer App</span></h2>
            <p>Live build progress, costs, site photos, and direct chat with the JSD team — all from your phone.</p>
            <a class="btn btn-gold" href="../download.html">Learn More</a>
          </div>
          <form class="auth-card" method="post" style="border-radius:24px">
            <h2 class="display-md" style="margin-bottom:20px">Change <span class="gold-text">password</span></h2>
            <input type="hidden" name="csrf" value="<?= portal_csrf() ?>">
            <input type="hidden" name="action" value="password">
            <div class="field" style="margin-bottom:16px">
              <label>Current Password</label>
              <input name="current" type="password" required>
            </div>
            <div class="field" style="margin-bottom:22px">
              <label>New Password (min 8 characters)</label>
              <input name="new" type="password" required minlength="8">
            </div>
            <button class="btn btn-gold" type="submit">Update Password</button>
          </form>
        </div>

        <div class="contact-card mt-4">
          <div>
            <b>Need help? Support &amp; maintenance</b>
            <p style="color:var(--text-dim);font-size:.92rem">
              Call/WhatsApp <a href="https://wa.me/61424475767" style="color:var(--gold-hi)">+61 424 475 767</a>
              or email <a href="mailto:support@jsdconstruction.com.au" style="color:var(--gold-hi)">support@jsdconstruction.com.au</a>
              — every JSD build includes a 12-month maintenance period and QBCC statutory structural warranty.</p>
          </div>
        </div>
      </div>
    </section>
  </main>
  <script src="../assets/js/cursor-fx.js"></script>
</body>
</html>
