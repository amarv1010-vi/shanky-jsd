<?php
/** Admin login page. Successful login redirects to dashboard.php. */

require_once __DIR__ . '/auth.php';

if (is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // simple brute-force delay
    usleep(400000);
    if (admin_login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Login — JSD Admin</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logos/favicon.svg">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="login-box">
    <div class="brand">JSD ADMIN<small>LUXURY BUILT IN AUSTRALIA</small></div>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <div>
        <label for="u">Username</label>
        <input id="u" name="username" type="text" required autofocus>
      </div>
      <div>
        <label for="p">Password</label>
        <input id="p" name="password" type="password" required>
      </div>
      <button class="btn btn-gold" type="submit" style="justify-content:center">Sign In</button>
    </form>
  </div>
</body>
</html>
