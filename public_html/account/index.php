<?php
/**
 * Login / Sign-up portal for the whole site.
 * - "Sign In" asks: Customer or JSD Admin.
 *     Admin    → verified against the users table (default admin / Delhi@1357,
 *                changeable in Admin > Change Password) → /admin/dashboard.php
 *     Customer → verified against the users table → account/dashboard.php
 * - "Create Account" registers a new customer (name, email, mobile, password).
 */

require_once __DIR__ . '/auth.php';

$mode = ($_POST['mode'] ?? $_GET['mode'] ?? 'login') === 'signup' ? 'signup' : 'login';
$error = '';
$notice = '';

if (current_customer()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_verify_csrf();
    usleep(350000); // basic brute-force damper

    if ($mode === 'signup') {
        $err = Users::registerCustomer(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['mobile'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if ($err === null) {
            $u = Users::findByUsername((string) $_POST['email'], 'customer');
            customer_login_session($u);
            header('Location: dashboard.php?welcome=1');
            exit;
        }
        $error = $err;
    } else {
        $role = ($_POST['role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
        $username = (string) ($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($role === 'admin') {
            require_once dirname(__DIR__) . '/admin/auth.php'; // reuses same session
            if (admin_login($username, $password)) {
                header('Location: ../admin/dashboard.php');
                exit;
            }
            $error = 'Invalid admin credentials.';
        } else {
            $u = Users::verify($username, $password, 'customer');
            if ($u) {
                customer_login_session($u);
                header('Location: dashboard.php');
                exit;
            }
            $error = 'Invalid email or password. New here? Create an account below.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-AU">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — JSD Construction Pty Ltd</title>
  <meta name="description" content="Sign in to your JSD Construction account to track your project, or create a new customer account.">
  <link rel="icon" type="image/svg+xml" href="../assets/img/logos/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <main>
    <section class="page-hero" style="padding-bottom:40px">
      <div class="hero-bg"></div>
      <div class="hero-grid-lines"></div>
      <div class="container center">
        <a href="../index.html"><img src="../assets/img/logos/logo-01-primary.svg" alt="JSD Construction logo" style="height:64px;margin:0 auto 18px"></a>
        <span class="eyebrow" style="justify-content:center">Client Portal</span>
        <h1 class="display-lg">Welcome to <span class="gold-text">JSD Construction</span></h1>
      </div>
    </section>
    <section class="section" style="padding-top:20px">
      <div class="container auth-shell">
        <div class="auth-card">
          <div class="auth-tabs">
            <a class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>" href="?mode=login">Sign In</a>
            <a class="auth-tab <?= $mode === 'signup' ? 'active' : '' ?>" href="?mode=signup">Create Account</a>
          </div>

          <?php if ($error): ?><div class="form-status err" style="display:block;margin:0 0 20px"><?= pe($error) ?></div><?php endif; ?>

          <?php if ($mode === 'login'): ?>
          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= portal_csrf() ?>">
            <input type="hidden" name="mode" value="login">
            <div class="role-toggle">
              <label><input type="radio" name="role" value="customer" checked> 👤 Customer</label>
              <label><input type="radio" name="role" value="admin"> 🔑 JSD Admin</label>
            </div>
            <div class="field" style="margin-bottom:16px">
              <label>Email / Username</label>
              <input name="username" required placeholder="you@email.com" autofocus>
            </div>
            <div class="field" style="margin-bottom:24px">
              <label>Password</label>
              <input name="password" type="password" required>
            </div>
            <button class="btn btn-gold" type="submit" style="width:100%">Sign In</button>
            <p style="color:var(--text-dim);font-size:.85rem;margin-top:18px;text-align:center">
              No account yet? <a href="?mode=signup" style="color:var(--gold-hi)">Create one free</a> to track your project.
            </p>
          </form>
          <?php else: ?>
          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= portal_csrf() ?>">
            <input type="hidden" name="mode" value="signup">
            <div class="field" style="margin-bottom:16px">
              <label>Full Name *</label>
              <input name="name" required placeholder="Your name" value="<?= pe($_POST['name'] ?? '') ?>">
            </div>
            <div class="field" style="margin-bottom:16px">
              <label>Email * (this becomes your username)</label>
              <input name="email" type="email" required placeholder="you@email.com" value="<?= pe($_POST['email'] ?? '') ?>">
            </div>
            <div class="field" style="margin-bottom:16px">
              <label>Mobile</label>
              <input name="mobile" type="tel" placeholder="04xx xxx xxx" value="<?= pe($_POST['mobile'] ?? '') ?>">
            </div>
            <div class="field" style="margin-bottom:24px">
              <label>Password * (min 8 characters)</label>
              <input name="password" type="password" required minlength="8">
            </div>
            <button class="btn btn-gold" type="submit" style="width:100%">Create My Account</button>
          </form>
          <?php endif; ?>
        </div>
        <p class="center" style="margin-top:26px"><a href="../index.html" style="color:var(--text-dim)">← Back to website</a></p>
      </div>
    </section>
  </main>
  <script src="../assets/js/cursor-fx.js"></script>
</body>
</html>
