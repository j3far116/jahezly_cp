<?php

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Twig\TwigFunction;

final class TwigService
{
    private static ?Environment $twig = null;

    public static function boot(string $viewsPath, array $options = []): void
    {
        $loader = new FilesystemLoader($viewsPath);
        $twig = new Environment($loader, [
            'autoescape' => 'html',
            'cache'      => false,
            ...$options,
        ]);

        if (!empty($options['debug'])) {
            $twig->addExtension(new DebugExtension());
        }

        // Globals
        $twig->addGlobal('session', $_SESSION);
        $twig->addGlobal('base_path', rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/'));

        // Functions (كلها داخل boot بعد إنشاء $twig)
        /* $twig->addFunction(new TwigFunction('csrf_field', fn() => \App\Core\Csrf::field(), ['is_safe'=>['html']]));
        $twig->addFunction(new TwigFunction('csrf_token', fn() => \App\Core\Csrf::token()));
        $twig->addFunction(new TwigFunction('flash', fn(string $key) => \App\Core\Session::flash($key)));
        $twig->addFunction(new TwigFunction('url', function (string $path): string {
            $bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp','/');
            $path = '/' . ltrim($path, '/');
            return $bp . $path;
        })); */

        // دوال Twig
        $twig->addFunction(new TwigFunction('csrf_field', fn() => \App\Core\Csrf::field(), ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('csrf_token', fn() => \App\Core\Csrf::token()));
        $twig->addFunction(new TwigFunction('url', function (string $path): string {
            $bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
            $path = '/' . ltrim($path, '/');
            return $bp . $path;
        }));
        // قراءة رسالة الفلاش ومسحها من الجلسة (one-shot)
        $twig->addFunction(new TwigFunction('flash', fn(string $key) => \App\Core\Session::flash($key)));

        // Globals مفيدة للنافبار
        $twig->addGlobal('site_name', $_ENV['SITE_NAME'] ?? 'لوحة الإدارة');
        $twig->addGlobal('current_path', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');


        self::$twig = $twig;
    }

    public static function view(): Environment
    {
        if (!self::$twig) {
            throw new \RuntimeException('Twig not booted');
        }
        return self::$twig;
    }

/*     public static function refreshGlobals(): void
    {
        if (self::$twig) {
            self::$twig->addGlobal('session', $_SESSION);
        }
    } */
      public static function refreshGlobals(): void
    {
        if (!self::$twig) {
            return;
        }

        // حدّث الـ session كل رندر (one-shot flashes وما إلى ذلك)
        self::$twig->addGlobal('session', $_SESSION);

        // اجلب المستخدم الحالي — عدّل السطر التالي حسب مشروعك
        // 1) إن كانت لديك خدمة Auth:
        $user = class_exists(\App\Services\Auth::class) ? \App\Services\Auth::user() : ($_SESSION['user'] ?? null);
        // 2) بديل: $user = Gate::user(); إن كانت لديك طريقة مشابهة.

        // ابنِ مصفوفة auth بشكل آمن (لا تمرّر كل الجلسة)
        $auth = [
            'is_authenticated' => (bool)$user,
            'id'    => $user['id']    ?? null,
            'name'  => $user['name']  ?? null,
            'email' => $user['email'] ?? null,
            'role'  => $user['role']  ?? 'guest', // admin / owner / cashier / guest
            // (اختياري) صلاحيات مشتقة إن رغبت:
            // 'permissions' => method_exists(Gate::class, 'permissionsFor') ? Gate::permissionsFor($user) : [],
        ];

        self::$twig->addGlobal('auth', $auth);
    }
}
