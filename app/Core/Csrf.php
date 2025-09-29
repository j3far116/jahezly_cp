<?php
namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        $t = self::token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }

   public static function check(?string $t): bool
{
    // دعم هيدر X-CSRF-Token إذا لم يصل حقل فورم
    if (!$t && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $t = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return is_string($t) && hash_equals($_SESSION['_csrf'] ?? '', $t);
}

}