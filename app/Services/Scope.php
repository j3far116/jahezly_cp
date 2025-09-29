<?php
namespace App\Services;

final class Scope
{
    /**
     * إن كان المستخدم ليس admin وكان لديه market_id => نعيده
     * غير ذلك نعيد null (أي لا يوجد تقييد).
     */
    public static function marketIdForCurrentUser(): ?int
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) return null;

        $role = $u['role'] ?? '';
        if (in_array($role, ['admin'], true)) {
            return null; // لا تقييد
        }

        $mid = $u['market_id'] ?? null;
        return $mid !== null ? (int)$mid : null;
    }
}