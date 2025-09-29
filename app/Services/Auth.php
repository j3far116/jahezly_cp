<?php
namespace App\Services;

use App\Models\User;

final class Auth
{
    private const WINDOW_SECONDS = 300;
    private const MAX_ATTEMPTS   = 5;
    private const COOKIE_NAME    = 'remember_token';

    private static function makeHash(string $token): string
    {
        $secret = $_ENV['APP_SECRET'] ?? '';
        return hash('sha256', $token . $secret);
    }

    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return false; }
        if (self::isRateLimited()) { return false; }

        $user = User::findByEmail($email);
        $ok = $user && password_verify($password, $user['password']);

        if ($ok) {
            $_SESSION['user'] = [
                'id'        => (int)$user['id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'mobile'    => $user['mobile'],
                'role'      => $user['role'],
                'market_id' => $user['market_id'],
                'status'    => $user['status'],
            ];
            self::resetAttempts();
            session_regenerate_id(true);

            if ($remember) {
                $token = bin2hex(random_bytes(32)); // cookie
                $hash  = self::makeHash($token);    // DB
                User::updateRememberHash((int)$user['id'], $hash);
                self::setRememberCookie($token);
            } else {
                User::updateRememberHash((int)$user['id'], null);
                self::clearRememberCookie();
            }
            return true;
        }

        self::bumpAttempts();
        return false;
    }

    public static function check(): bool
    { return !empty($_SESSION['user']); }

    public static function user(): ?array
    { return $_SESSION['user'] ?? null; }

    public static function logout(): void
    {
        if (!empty($_SESSION['user']['id'])) {
            User::updateRememberHash((int)$_SESSION['user']['id'], null);
        }
        unset($_SESSION['user']);
        self::clearRememberCookie();
        session_regenerate_id(true);
    }

    public static function initRememberedLogin(): void
    {
        if (self::check()) { return; }
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$token || !is_string($token) || strlen($token) < 16) { return; }
        $hash = self::makeHash($token);

        $u = User::findByRememberHash($hash);
        if ($u) {
            $_SESSION['user'] = [
                'id'        => (int)$u['id'],
                'name'      => $u['name'],
                'email'     => $u['email'],
                'mobile'    => $u['mobile'],
                'role'      => $u['role'],
                'market_id' => $u['market_id'],
                'status'    => $u['status'],
            ];
            // rotate token
            $new = bin2hex(random_bytes(32));
            User::updateRememberHash((int)$u['id'], self::makeHash($new));
            self::setRememberCookie($new);
            session_regenerate_id(true);
        }
    }

    private static function isRateLimited(): bool
    {
        $r = $_SESSION['_login_rate'] ?? ['count'=>0,'start'=>time()];
        if (time() - $r['start'] > self::WINDOW_SECONDS) { $r = ['count'=>0,'start'=>time()]; }
        $_SESSION['_login_rate'] = $r; return $r['count'] >= self::MAX_ATTEMPTS;
    }
    private static function bumpAttempts(): void
    {
        $r = $_SESSION['_login_rate'] ?? ['count'=>0,'start'=>time()];
        if (time() - $r['start'] > self::WINDOW_SECONDS) { $r = ['count'=>0,'start'=>time()]; }
        $r['count']++; $_SESSION['_login_rate']=$r;
    }
    private static function resetAttempts(): void
    { $_SESSION['_login_rate'] = ['count'=>0,'start'=>time()]; }

    private static function setRememberCookie(string $token): void
    {
        $days   = (int)($_ENV['REMEMBER_DAYS'] ?? 30);
        $expire = time() + ($days * 86400);
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => $expire,
            'path'     => $_ENV['BASE_PATH'] ?? '/admincp',
            'domain'   => '',
            'secure'   => (($_ENV['SESSION_SECURE'] ?? 'false') === 'true'),
            'httponly' => true,
            'samesite' => $_ENV['SESSION_SAMESITE'] ?? 'Lax',
        ]);
    }

    private static function clearRememberCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => $_ENV['BASE_PATH'] ?? '/admincp',
            'domain'   => '',
            'secure'   => (($_ENV['SESSION_SECURE'] ?? 'false') === 'true'),
            'httponly' => true,
            'samesite' => $_ENV['SESSION_SAMESITE'] ?? 'Lax',
        ]);
    }
}