<?php
/**
 * Admin authentication (session-based, single admin user from config.php).
 * Include at the top of every admin page; call require_admin() to gate access.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';

global $CONFIG;

session_name($CONFIG['admin']['session_name']);
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']),
]);

function is_admin(): bool
{
    return !empty($_SESSION['jsd_admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}

function admin_login(string $username, string $password): bool
{
    global $CONFIG;
    $a = $CONFIG['admin'];
    if (hash_equals($a['username'], $username) && password_verify($password, $a['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['jsd_admin'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        return true;
    }
    return false;
}

function admin_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token — go back and retry.');
    }
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
