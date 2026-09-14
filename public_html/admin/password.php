<?php
/** Change the admin password (stored in the users table). */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials/layout.php';
require_admin();

$flash = '';
$flashKind = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        Users::ensureTable();
        $adminId = (int) ($_SESSION['jsd_admin_id'] ?? 0);
        if (!$adminId) {
            // logged in via config fallback — locate (or seed) the DB admin row
            $row = db()->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetch();
            $adminId = (int) ($row['id'] ?? 0);
        }
        if (!$adminId) {
            $flash = 'No admin account found in the database.';
            $flashKind = 'err';
        } else {
            $err = Users::changePassword($adminId, (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''));
            if ($err === null) {
                $_SESSION['jsd_admin_id'] = $adminId;
                $flash = 'Admin password updated. Use the new password next time you sign in.';
            } else {
                $flash = $err;
                $flashKind = 'err';
            }
        }
    } catch (Throwable $e) {
        error_log('admin password change: ' . $e->getMessage());
        $flash = 'Something went wrong updating the password.';
        $flashKind = 'err';
    }
}

admin_header('Change Password', 'password');
?>
<h1>Change Password</h1>
<p class="sub">Updates the admin login used at <code>/account/</code> and <code>/admin/</code>. Minimum 8 characters — pick something strong and unique.</p>
<?php if ($flash): ?><div class="flash <?= $flashKind ?>"><?= e($flash) ?></div><?php endif; ?>

<form class="panel" method="post" style="max-width:480px">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div style="margin-bottom:16px">
    <label>Current Password</label>
    <input name="current" type="password" required>
  </div>
  <div style="margin-bottom:22px">
    <label>New Password (min 8 characters)</label>
    <input name="new" type="password" required minlength="8">
  </div>
  <button class="btn btn-gold" type="submit">Update Password</button>
</form>
<?php admin_footer(); ?>
