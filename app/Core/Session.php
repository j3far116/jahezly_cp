<?php
namespace App\Core;

final class Session
{
    public static function start(): void
    {
        session_name($_ENV['SESSION_NAME'] ?? 'APPSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $_ENV['BASE_PATH'] ?? '/admincp',
            'domain' => '',
            'secure' => (($_ENV['SESSION_SECURE'] ?? 'false') === 'true'),
            'httponly' => true,
            'samesite' => $_ENV['SESSION_SAMESITE'] ?? 'Lax',
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // Regenerate ID every 5 min
        if (!isset($_SESSION['_last_regen'])) { $_SESSION['_last_regen'] = time(); }
        if (time() - ($_SESSION['_last_regen'] ?? 0) > 300) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }

        // Inactivity timeout
        $ttl = (int)($_ENV['SESSION_IDLE_TTL'] ?? 2700);
        $now = time();
        if (isset($_SESSION['_last_seen']) && ($now - $_SESSION['_last_seen']) > $ttl) {
            unset($_SESSION['user']);
        }
        $_SESSION['_last_seen'] = $now;
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        if ($value === null) {
            $val = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $val;
        }
        $_SESSION['_flash'][$key] = $value; return null;
    }
}