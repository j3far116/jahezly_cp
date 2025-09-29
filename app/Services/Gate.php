<?php
namespace App\Services;

final class Gate
{
    public static function allow(array $roles): void
    {
        $bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $u  = Auth::user();

        if (!$u) {
            header('Location: ' . $bp . '/login');
            exit;
        }

        if (!in_array($u['role'], $roles, true)) {
            \App\Core\Session::flash('error', 'غير مصرح لك بعرض هذه الصفحة');
            header('Location: ' . $bp . '/dashboard');
            exit;
        }
    }
}