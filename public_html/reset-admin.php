<?php
/**
 * ONE-TIME ADMIN PASSWORD RESET.
 *
 * Use this if you forgot the admin login. Upload this file to the same folder
 * as index.php (your site root), then visit:
 *     https://www.jsdconstruction.com.au/reset-admin.php
 * Set a new username + password. It updates BOTH the login stores (config file
 * and the users table) and then DELETES ITSELF so it can't be misused.
 *
 * Security: only someone with cPanel/File-Manager access can place this file,
 * and it self-destructs after one successful reset.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$configPath = __DIR__ . '/api/config.php';
if (!file_exists($configPath)) {
    exit('<p style="font-family:sans-serif">Site is not installed yet (api/config.php missing). Run install.php first.</p>');
}
$CONFIG = require $configPath;

function e2($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$done = false;
$error = '';
$newUser = $CONFIG['admin']['username'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUser = trim($_POST['username'] ?? '') ?: 'admin';
    $pass    = (string) ($_POST['password'] ?? '');
    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        // 1) update the config.php fallback admin block
        $CONFIG['admin']['username'] = $newUser;
        $CONFIG['admin']['password_hash'] = $hash;
        $php = "<?php\n// Updated by reset-admin.php on " . date('c') . " — keep private.\nreturn "
             . var_export($CONFIG, true) . ";\n";
        $wroteConfig = @file_put_contents($configPath, $php) !== false;

        // 2) update (or create) the admin row in the users table
        $wroteDb = false;
        try {
            $c = $CONFIG['db'];
            $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}", $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
                username VARCHAR(190) NOT NULL,
                name VARCHAR(120) NULL, email VARCHAR(190) NULL, mobile VARCHAR(30) NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY uq_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $sel = $pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($sel) {
                $stmt = $pdo->prepare("UPDATE users SET username=?, password_hash=? WHERE id=?");
                $stmt->execute([$newUser, $hash, (int) $sel['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (role, username, password_hash) VALUES ('admin', ?, ?)");
                $stmt->execute([$newUser, $hash]);
            }
            $wroteDb = true;
        } catch (Throwable $ex) {
            // config fallback still lets them in even if DB update failed
            error_log('reset-admin db: ' . $ex->getMessage());
        }

        if ($wroteConfig || $wroteDb) {
            $done = true;
            @unlink(__FILE__); // self-destruct
        } else {
            $error = 'Could not write changes. Check that api/config.php is writable (permissions 644).';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Reset Admin Password — JSD</title>
<style>
  body{background:#100E0B;color:#EDE6DA;font:15px/1.6 "Segoe UI",sans-serif;margin:0;padding:8vh 16px}
  .box{max-width:440px;margin:0 auto;background:#1F1B14;border:1px solid rgba(207,161,104,.25);border-radius:16px;padding:40px}
  h1{font-family:Georgia,serif;font-weight:normal;color:#E0B171;margin:0 0 8px}
  .sub{color:#A89C8A;font-size:.9rem;margin-bottom:24px}
  label{display:block;font-size:.72rem;letter-spacing:2px;text-transform:uppercase;color:#CFA168;margin:16px 0 6px;font-weight:700}
  input{width:100%;box-sizing:border-box;padding:12px 14px;background:rgba(16,14,11,.65);color:#EDE6DA;border:1px solid rgba(207,161,104,.25);border-radius:8px;font:inherit}
  input:focus{outline:none;border-color:#CFA168}
  button{margin-top:24px;width:100%;padding:14px;border:0;border-radius:99px;font-weight:700;font-size:1rem;cursor:pointer;background:linear-gradient(135deg,#7B613A,#C1955D,#E0B171,#CFA168,#9F7745);color:#17130C}
  .ok{background:rgba(110,168,116,.12);border:1px solid rgba(110,168,116,.4);color:#A8D8AE;border-radius:9px;padding:16px;font-size:.95rem}
  .err{background:rgba(178,84,84,.12);border:1px solid rgba(178,84,84,.4);color:#E5A0A0;border-radius:9px;padding:12px 14px;margin-bottom:14px;font-size:.9rem}
  a{color:#E0B171}
</style></head><body>
<div class="box">
  <h1>Reset Admin Password</h1>
  <?php if ($done): ?>
    <div class="ok"><b>✓ Done.</b> Your admin login has been reset.<br><br>
      Username: <b><?= e2($newUser) ?></b><br>
      Password: the one you just chose.<br><br>
      This reset file has deleted itself.<br>
      <a href="admin/"><b>→ Go to the admin login</b></a>
    </div>
  <?php else: ?>
    <div class="sub">Set a new admin username and password. This file removes itself once done.</div>
    <?php if ($error): ?><div class="err"><?= e2($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <label>Admin Username</label>
      <input name="username" value="<?= e2($newUser) ?>" required>
      <label>New Password (min 8 characters)</label>
      <input name="password" type="password" minlength="8" required autofocus>
      <button type="submit">Reset Password</button>
    </form>
  <?php endif; ?>
</div>
</body></html>
