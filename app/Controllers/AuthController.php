<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Session;
use App\Core\Csrf;
use App\Services\Auth;

final class AuthController
{
    public function showLogin(): void
    {
        $bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp','/');

        // ✅ إن كان مسجلاً بالفعل، حوّله حسب دوره
        $u = Auth::user();
        if (!empty($u)) {
            header('Location: ' . $this->homeUrlForRole($u, $bp));
            return;
        }

        \App\Core\TwigService::refreshGlobals();
        echo TwigService::view()->render('auth/login.twig', [
            '_csrf' => Csrf::token(),
        ]);
    }

    public function login(): void
    {
        $bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp','/');

        $csrf = $_POST['_csrf'] ?? null;
        if (!Csrf::check($csrf)) {
            http_response_code(400);
            Session::flash('error', 'طلب غير صالح.');
            header('Location: ' . $bp . '/login'); return;
        }

        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            Session::flash('error','البريد وكلمة المرور مطلوبة.');
            header('Location: ' . $bp . '/login'); return;
        }

        $ok = Auth::attempt($email, $password, $remember);
        if (!$ok) {
            Session::flash('error','بيانات الدخول غير صحيحة أو الحساب غير مسموح.');
            header('Location: ' . $bp . '/login'); return;
        }

        // ✅ بعد الدخول، حوّل حسب الدور
        $u = Auth::user();
        Session::flash('success','تم تسجيل الدخول بنجاح.');
        header('Location: ' . $this->homeUrlForRole($u, $bp));
    }

    public function logout(): void
    {
        $bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp','/');
        $csrf = $_POST['_csrf'] ?? null;
        if (!Csrf::check($csrf)) {
            http_response_code(400);
            Session::flash('error', 'طلب غير صالح.');
            header('Location: ' . $bp . '/login'); return;
        }
        Auth::logout();
        \App\Core\TwigService::refreshGlobals();
        Session::flash('success', 'تم تسجيل الخروج.');
        header('Location: ' . $bp . '/login');
    }

    /**
     * تحديد الصفحة الرئيسية حسب الدور
     * admin  → /dashboard
     * owner  → /markets/{market_id} إن وجد، وإلا /markets
     * user   → /dashboard
     */
    private function homeUrlForRole(array $u, string $bp): string
    {
        $role = (string)($u['role'] ?? 'user');
        $mid  = $u['market_id'] ?? null;

        switch ($role) {
            case 'admin':
                return $bp . '/dashboard';

            case 'owner':
                if (!empty($mid)) return $bp . '/markets/' . (int)$mid;
                return $bp . '/markets';

            case 'user':
            default:
                return $bp . '/dashboard';
        }
    }
}
