<?php
/**
 * JSD Construction — one-time web installer.
 *
 * HOW TO USE (after extracting the site zip into your domain's folder):
 *   1. In cPanel > MySQL® Databases: create a database + user, add user to DB (ALL PRIVILEGES).
 *   2. Visit https://www.jsdconstruction.com.au/install.php
 *   3. Fill the form. The installer will:
 *        - test the database connection
 *        - create all tables + seed content (from install-schema.sql)
 *        - write api/config.php (with a hashed admin password)
 *        - DELETE itself and install-schema.sql when finished.
 *
 * SECURITY: refuses to run if api/config.php already exists.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$configPath = __DIR__ . '/api/config.php';
$schemaPath = __DIR__ . '/install-schema.sql';

if (file_exists($configPath)) {
    http_response_code(403);
    exit('<h2 style="font-family:sans-serif">Already installed</h2><p style="font-family:sans-serif">api/config.php exists. Delete it first if you really want to re-install. <a href="index.html">Go to website</a></p>');
}

function field(string $k): string { return trim((string) ($_POST[$k] ?? '')); }

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = field('db_host') ?: 'localhost';
    $dbName = field('db_name');
    $dbUser = field('db_user');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $adminUser = field('admin_user') ?: 'jsdadmin';
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $smtpUser = field('smtp_user') ?: 'info@jsdconstruction.com.au';
    $smtpPass = (string) ($_POST['smtp_pass'] ?? '');

    if ($dbName === '' || $dbUser === '') $errors[] = 'Database name and user are required.';
    if (strlen($adminPass) < 8) $errors[] = 'Admin password must be at least 8 characters.';

    $pdo = null;
    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            $errors[] = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
        }
    }

    if (!$errors && $pdo) {
        // run schema (skip if tables already exist from a previous attempt)
        try {
            $schema = @file_get_contents($schemaPath);
            if ($schema === false) {
                $errors[] = 'install-schema.sql not found next to install.php.';
            } else {
                $already = $pdo->query("SHOW TABLES LIKE 'enquiries'")->fetch();
                if (!$already) {
                    // strip full-line SQL comments first, THEN split on ';' so that
                    // comment lines sitting above a CREATE/INSERT never swallow it.
                    $clean = preg_replace('/^\s*--.*$/m', '', $schema);
                    foreach (explode(';', $clean) as $stmt) {
                        $stmt = trim($stmt);
                        if ($stmt !== '') {
                            $pdo->exec($stmt);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'Schema import failed: ' . htmlspecialchars($e->getMessage());
        }
    }

    if (!$errors) {
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $export = var_export([
            'db' => ['host' => $dbHost, 'name' => $dbName, 'user' => $dbUser, 'pass' => $dbPass, 'charset' => 'utf8mb4'],
            'admin' => ['username' => $adminUser, 'password_hash' => $hash, 'session_name' => 'jsd_admin_session'],
            'smtp' => [
                'enabled' => ($smtpPass !== ''), 'host' => 'smtp.titan.email', 'port' => 465, 'secure' => 'ssl',
                'username' => $smtpUser, 'password' => $smtpPass,
                'from' => $smtpUser, 'from_name' => 'JSD Construction Pty Ltd',
            ],
            'notify' => [
                'enquiry' => 'contact@jsdconstruction.com.au', 'contact' => 'contact@jsdconstruction.com.au',
                'quotes' => 'quotes@jsdconstruction.com.au', 'projects' => 'projects@jsdconstruction.com.au',
                'support' => 'support@jsdconstruction.com.au', 'info' => 'info@jsdconstruction.com.au',
            ],
            'whatsapp' => [
                'enabled' => false, 'phone_number_id' => '', 'access_token' => '',
                'template_name' => 'enquiry_received', 'template_lang' => 'en_AU',
            ],
            'uploads' => [
                'dir' => __DIR__ . '/uploads/projects', 'max_bytes' => 10485760,
                'ratio_w' => 3, 'ratio_h' => 2, 'out_width' => 1500,
            ],
            'site' => [
                'name' => 'JSD Construction Pty Ltd', 'url' => 'https://www.jsdconstruction.com.au',
                'phone' => '+61 424 475 767', 'wa_link' => 'https://wa.me/61424475767',
                'instagram' => 'https://www.instagram.com/jsd.construction/',
            ],
        ], true);

        $php = "<?php\n// Generated by install.php on " . date('c') . " — keep private.\nreturn " . $export . ";\n";
        if (@file_put_contents($configPath, $php) === false) {
            $errors[] = 'Could not write api/config.php — check folder permissions (755) and try again.';
        } else {
            @chmod($configPath, 0640);
            $done = true;
            // clean up installer artefacts
            @unlink($schemaPath);
            @unlink(__FILE__);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>JSD Website Installer</title>
<style>
  body{background:#100E0B;color:#EDE6DA;font:15px/1.6 "Segoe UI",sans-serif;margin:0;padding:40px 16px}
  .box{max-width:560px;margin:0 auto;background:#1F1B14;border:1px solid rgba(207,161,104,.25);border-radius:16px;padding:38px}
  h1{font-family:Georgia,serif;font-weight:normal;color:#E0B171;letter-spacing:1px;margin:0 0 6px}
  .sub{color:#A89C8A;font-size:.9rem;margin-bottom:26px}
  h2{font-family:Georgia,serif;font-weight:normal;font-size:1.05rem;color:#CFA168;border-top:1px solid rgba(207,161,104,.18);padding-top:22px;margin:26px 0 12px}
  label{display:block;font-size:.72rem;letter-spacing:2px;text-transform:uppercase;color:#CFA168;margin:14px 0 6px;font-weight:700}
  input{width:100%;box-sizing:border-box;padding:12px 14px;background:rgba(16,14,11,.65);color:#EDE6DA;border:1px solid rgba(207,161,104,.25);border-radius:8px;font:inherit}
  input:focus{outline:none;border-color:#CFA168}
  .help{color:#8d8172;font-size:.78rem;margin-top:4px}
  button{margin-top:28px;width:100%;padding:15px;border:0;border-radius:99px;font-weight:700;font-size:1rem;cursor:pointer;background:linear-gradient(135deg,#7B613A,#C1955D,#E0B171,#CFA168,#9F7745);color:#17130C}
  .err{background:rgba(178,84,84,.12);border:1px solid rgba(178,84,84,.4);color:#E5A0A0;border-radius:9px;padding:13px 16px;margin-bottom:8px;font-size:.9rem}
  .ok{background:rgba(110,168,116,.12);border:1px solid rgba(110,168,116,.4);color:#A8D8AE;border-radius:9px;padding:16px 18px;font-size:.95rem}
  a{color:#E0B171}
</style>
</head>
<body>
<div class="box">
  <h1>JSD CONSTRUCTION</h1>
  <div class="sub">One-time website installer · Luxury Built in Australia</div>

  <?php if ($done): ?>
    <div class="ok">
      <b>✓ Installation complete.</b><br><br>
      • Database tables created with starter content<br>
      • <code>api/config.php</code> written (admin password stored as a secure hash)<br>
      • Installer files deleted automatically<br><br>
      <a href="index.html"><b>→ Open your website</b></a><br>
      <a href="admin/"><b>→ Open the admin panel</b></a> (username: <?= htmlspecialchars(field('admin_user') ?: 'jsdadmin') ?>)
    </div>
  <?php else: ?>
    <?php foreach ($errors as $e): ?><div class="err"><?= $e ?></div><?php endforeach; ?>
    <form method="post" autocomplete="off">
      <h2 style="border:0;padding-top:0;margin-top:0">1 · Database (from cPanel → MySQL® Databases)</h2>
      <label>DB Host</label><input name="db_host" value="<?= htmlspecialchars(field('db_host') ?: 'localhost') ?>">
      <label>Database Name *</label><input name="db_name" value="<?= htmlspecialchars(field('db_name')) ?>" placeholder="e.g. reeaxzsor5hf_jsd" required>
      <label>Database User *</label><input name="db_user" value="<?= htmlspecialchars(field('db_user')) ?>" placeholder="e.g. reeaxzsor5hf_jsdadmin" required>
      <label>Database Password *</label><input name="db_pass" type="password" required>

      <h2>2 · Admin panel login (choose now)</h2>
      <label>Admin Username</label><input name="admin_user" value="<?= htmlspecialchars(field('admin_user') ?: 'jsdadmin') ?>">
      <label>Admin Password * (min 8 chars)</label><input name="admin_pass" type="password" required minlength="8">

      <h2>3 · Automated emails (Titan)</h2>
      <label>Sender Mailbox</label><input name="smtp_user" value="<?= htmlspecialchars(field('smtp_user') ?: 'info@jsdconstruction.com.au') ?>">
      <label>Mailbox Password</label><input name="smtp_pass" type="password">
      <div class="help">The password of that Titan mailbox. Leave blank to skip for now — forms will still save to the database; add the password later in api/config.php to switch emails on.</div>

      <button type="submit">Install Website</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
