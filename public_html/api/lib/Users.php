<?php
/**
 * User accounts (admin + customers) backed by the `users` table.
 * Self-migrating: ensure_users_table() creates the table and seeds the
 * default admin (admin / Delhi@1357) on sites installed before this
 * feature existed, so no manual SQL is ever needed.
 */

class Users
{
    public static function ensureTable(): void
    {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS users (
              id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              role          ENUM('admin','customer') NOT NULL DEFAULT 'customer',
              username      VARCHAR(190)  NOT NULL,
              name          VARCHAR(120)  NULL,
              email         VARCHAR(190)  NULL,
              mobile        VARCHAR(30)   NULL,
              password_hash VARCHAR(255)  NOT NULL,
              created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $hasAdmin = db()->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
        if (!$hasAdmin) {
            db()->prepare(
                "INSERT INTO users (role, username, name, email, password_hash) VALUES ('admin', 'admin', 'JSD Administrator', 'contact@jsdconstruction.com.au', ?)"
            )->execute([password_hash('Delhi@1357', PASSWORD_DEFAULT)]);
        }
    }

    public static function findByUsername(string $username, ?string $role = null): ?array
    {
        self::ensureTable();
        $sql = 'SELECT * FROM users WHERE username = ?';
        $args = [mb_strtolower(trim($username))];
        if ($role !== null) { $sql .= ' AND role = ?'; $args[] = $role; }
        $stmt = db()->prepare($sql);
        $stmt->execute($args);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function verify(string $username, string $password, string $role): ?array
    {
        $u = self::findByUsername($username, $role);
        if ($u && password_verify($password, $u['password_hash'])) {
            return $u;
        }
        return null;
    }

    /** @return string|null error message, null on success */
    public static function registerCustomer(string $name, string $email, string $mobile, string $password): ?string
    {
        self::ensureTable();
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
        if (strlen($password) < 8) return 'Password must be at least 8 characters.';
        if (trim($name) === '') return 'Please enter your name.';
        if (self::findByUsername($email)) return 'An account with this email already exists — try signing in.';

        db()->prepare(
            "INSERT INTO users (role, username, name, email, mobile, password_hash)
             VALUES ('customer', ?, ?, ?, ?, ?)"
        )->execute([
            $email,
            mb_substr(trim($name), 0, 120),
            $email,
            mb_substr(trim($mobile), 0, 30),
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        return null;
    }

    /** @return string|null error message, null on success */
    public static function changePassword(int $userId, string $current, string $new): ?string
    {
        self::ensureTable();
        if (strlen($new) < 8) return 'New password must be at least 8 characters.';
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            return 'Current password is incorrect.';
        }
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        return null;
    }
}
