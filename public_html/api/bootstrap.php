<?php
/**
 * JSD Construction — shared API bootstrap.
 * Loads config, opens PDO, provides JSON + validation helpers.
 * Every api/*.php endpoint and the admin panel includes this file.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak stack traces to visitors
ini_set('log_errors', '1');

define('JSD_ROOT', dirname(__DIR__));

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    // Allow the static site to degrade gracefully before config.php exists.
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Server not configured yet (api/config.php missing).']);
    exit;
}
$CONFIG = require $configFile;

/** Lazily-created shared PDO connection. */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $c = $CONFIG['db'];
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Read a JSON (or form-encoded) POST body into an array. */
function read_input(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_response(['ok' => false, 'error' => 'POST required'], 405);
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        $data = $_POST; // fallback for form-encoded submissions
    }
    return array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $f) {
        if (!isset($data[$f]) || $data[$f] === '') {
            json_response(['ok' => false, 'error' => "Missing required field: {$f}"], 422);
        }
    }
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[+()\d\s\-]{8,18}$/', $phone);
}

/** Very small honeypot / rate limiter: max 10 posts per IP per hour per table. */
function throttle(string $table): void
{
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) AS c FROM {$table} WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)"
        );
        $stmt->execute([client_ip()]);
        if ((int) $stmt->fetch()['c'] >= 10) {
            json_response(['ok' => false, 'error' => 'Too many submissions — please try again later.'], 429);
        }
    } catch (Throwable $e) {
        // table may not track ip; don't block submission on throttle failure
    }
}

function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

require_once __DIR__ . '/lib/Mailer.php';
require_once __DIR__ . '/lib/WhatsApp.php';
require_once __DIR__ . '/lib/Users.php';
