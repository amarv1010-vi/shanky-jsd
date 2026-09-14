<?php
/**
 * Customer-portal session helpers. Shares the same PHP session as the
 * admin panel (one cookie, different keys: jsd_admin vs jsd_customer_id).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';

global $CONFIG;

if (session_status() === PHP_SESSION_NONE) {
    session_name($CONFIG['admin']['session_name']);
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

function current_customer(): ?array
{
    if (empty($_SESSION['jsd_customer_id'])) return null;
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([(int) $_SESSION['jsd_customer_id']]);
    return $stmt->fetch() ?: null;
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        header('Location: index.php');
        exit;
    }
    return $c;
}

function customer_login_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['jsd_customer_id'] = (int) $user['id'];
}

function portal_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function portal_verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token — go back and retry.');
    }
}

function pe(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
