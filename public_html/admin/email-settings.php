<?php
/**
 * Email Settings — self-service SMTP configuration + live test.
 * Lets the admin turn on automated emails (Titan SMTP) without editing files,
 * and send a real test email to confirm delivery before going live.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials/layout.php';
require_admin();

global $CONFIG;
$configPath = __DIR__ . '/../api/config.php';
$flash = '';
$flashKind = 'ok';

/** Rewrite api/config.php with an updated smtp block, preserving everything else. */
function save_config(array $config, string $path): bool
{
    $php = "<?php\n// Updated by admin/email-settings.php on " . date('c') . " — keep private.\nreturn "
         . var_export($config, true) . ";\n";
    return @file_put_contents($path, $php) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // apply posted values onto the live config (used by both Save and Test)
    $CONFIG['smtp']['enabled']   = true;
    $CONFIG['smtp']['host']      = trim($_POST['host'] ?? 'smtp.titan.email') ?: 'smtp.titan.email';
    $CONFIG['smtp']['port']      = (int) ($_POST['port'] ?? 465) ?: 465;
    $CONFIG['smtp']['secure']    = ($_POST['secure'] ?? 'ssl') === 'tls' ? 'tls' : 'ssl';
    $CONFIG['smtp']['username']  = trim($_POST['username'] ?? '');
    if (($_POST['password'] ?? '') !== '') {
        $CONFIG['smtp']['password'] = (string) $_POST['password'];
    }
    $CONFIG['smtp']['from']      = trim($_POST['from'] ?? '') ?: $CONFIG['smtp']['username'];
    $CONFIG['smtp']['from_name'] = trim($_POST['from_name'] ?? 'JSD Construction Pty Ltd') ?: 'JSD Construction Pty Ltd';

    if ($CONFIG['smtp']['username'] === '' || $CONFIG['smtp']['password'] === '' || $CONFIG['smtp']['password'] === 'CHANGE_ME_TITAN_MAILBOX_PASSWORD') {
        $flash = 'Please enter the mailbox address and its password.';
        $flashKind = 'err';
    } elseif ($action === 'test') {
        // send a real test email to the address provided
        $to = trim($_POST['test_to'] ?? '') ?: $CONFIG['smtp']['from'];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $flash = 'Enter a valid "send test to" email address.';
            $flashKind = 'err';
        } else {
            $html = Mailer::template(
                'Test email from your JSD website',
                '<p>Success — your automated emails are working! 🎉</p>
                 <p>This is a live test sent through Titan SMTP from your admin panel.
                 Customers will now receive a branded thank-you email the moment they submit an enquiry.</p>'
            );
            // capture the SMTP error if it fails
            $ok = Mailer::send($to, 'JSD Construction — email test ✓', $html);
            if ($ok) {
                // persist the working settings so they stay on
                save_config($CONFIG, $configPath);
                $flash = "Test email sent to {$to} and settings saved. Check that inbox (and Spam just in case) — it should arrive within a minute.";
            } else {
                $flash = 'The test email could NOT be sent. Double-check the mailbox password and that the username is the full email address (e.g. gurpreetsingh@jsdconstruction.com.au). Details were written to the server error log.';
                $flashKind = 'err';
            }
        }
    } elseif ($action === 'save') {
        if (save_config($CONFIG, $configPath)) {
            $flash = 'Email settings saved. Automated emails are now ON. Use "Send Test" to confirm delivery.';
        } else {
            $flash = 'Could not write api/config.php — check the file is writable (permissions 644).';
            $flashKind = 'err';
        }
    }
}

$s = $CONFIG['smtp'];
$adminEmail = 'gurpreetsingh@jsdconstruction.com.au';

admin_header('Email Settings', 'email');
?>
<h1>Email Settings</h1>
<p class="sub">Turn on automated customer emails (the "thank you for contacting" auto-reply) via your Titan mailbox. Enter your details, click <b>Send Test</b>, and confirm it lands in your inbox.</p>
<?php if ($flash): ?><div class="flash <?= $flashKind ?>"><?= e($flash) ?></div><?php endif; ?>

<form class="panel" method="post" style="max-width:620px">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

  <div class="section-title" style="margin-top:0">Titan SMTP</div>
  <div class="grid">
    <div>
      <label>Mailbox address (SMTP username) *</label>
      <input name="username" required value="<?= e($s['username'] ?: $adminEmail) ?>" placeholder="gurpreetsingh@jsdconstruction.com.au">
      <p class="help">Your real Titan mailbox — the one you log into Titan with.</p>
    </div>
    <div>
      <label>Mailbox password *</label>
      <input name="password" type="password" placeholder="<?= $s['enabled'] && $s['password'] ? '•••••• (leave blank to keep current)' : 'your Titan mailbox password' ?>">
      <p class="help">Same password you use in the Titan app / webmail.</p>
    </div>
    <div>
      <label>Send-from address</label>
      <input name="from" value="<?= e($s['from'] ?: 'info@jsdconstruction.com.au') ?>" placeholder="info@jsdconstruction.com.au">
      <p class="help">Shown as the sender. Can be any alias of your account (info@, contact@…).</p>
    </div>
    <div>
      <label>Sender name</label>
      <input name="from_name" value="<?= e($s['from_name'] ?: 'JSD Construction Pty Ltd') ?>">
    </div>
    <div>
      <label>Server</label>
      <input name="host" value="<?= e($s['host'] ?: 'smtp.titan.email') ?>">
    </div>
    <div>
      <label>Port / Security</label>
      <select name="port" onchange="document.getElementsByName('secure')[0].value = this.value==='587'?'tls':'ssl'">
        <option value="465" <?= (int)$s['port'] === 465 ? 'selected' : '' ?>>465 (SSL — recommended)</option>
        <option value="587" <?= (int)$s['port'] === 587 ? 'selected' : '' ?>>587 (TLS)</option>
      </select>
      <input type="hidden" name="secure" value="<?= e($s['secure'] ?: 'ssl') ?>">
    </div>
  </div>

  <div class="section-title">Send a test</div>
  <div class="grid">
    <div class="full">
      <label>Send test email to</label>
      <input name="test_to" type="email" value="<?= e($adminEmail) ?>" placeholder="you@email.com">
    </div>
  </div>

  <div style="display:flex;gap:12px;margin-top:22px;flex-wrap:wrap">
    <button class="btn btn-gold" type="submit" name="action" value="test">Save &amp; Send Test Email</button>
    <button class="btn btn-ghost" type="submit" name="action" value="save">Save Only</button>
  </div>
</form>

<div class="panel" style="max-width:620px">
  <div class="section-title" style="margin-top:0">Current status</div>
  <p style="color:var(--dim);font-size:.92rem">
    Automated emails: <b style="color:<?= $s['enabled'] ? '#A8D8AE' : '#E5A0A0' ?>"><?= $s['enabled'] ? 'ON' : 'OFF' ?></b><br>
    Sending as: <b><?= e($s['from'] ?: '—') ?></b> via <?= e($s['host'] ?: 'smtp.titan.email') ?>:<?= (int)$s['port'] ?><br>
  </p>
  <p class="help">If a test fails: the #1 cause is a wrong mailbox password, or using an alias (info@) as the username instead of your real mailbox (gurpreetsingh@…). Titan lets you <em>send from</em> aliases, but you must <em>log in / authenticate</em> with the real mailbox.</p>
</div>
<?php admin_footer(); ?>
