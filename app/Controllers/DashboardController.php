<?php
namespace App\Controllers;

use App\Core\TwigService; use App\Services\Auth; use App\Services\Gate;

final class DashboardController
{
    public function index(): void
    {
    \App\Services\Gate::allow(['admin','owner']);

    $bp  = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $mid = \App\Services\Scope::marketIdForCurrentUser(); // يعيد id متجر المالك أو null للمدير

    if ($mid !== null) {
        // المالك → صفحة المتجر مباشرة
        header('Location: ' . $bp . '/markets/' . $mid);
        return;
    }

    // المدير → اعرض لوحة التحكم المعتادة
    \App\Core\TwigService::refreshGlobals();
    echo \App\Core\TwigService::view()->render('dashboard/index.twig', [
        // مرّر ما تحتاجه للوحة التحكم
    ]);
    }
}