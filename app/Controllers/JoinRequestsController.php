<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Session;
use App\Core\Csrf;
use App\Services\Gate;
use App\Core\DB;

final class JoinRequestsController
{
    public function index(): void
    {
        Gate::allow(['admin']); // مدير الموقع فقط

        $stmt = DB::pdo()->query("SELECT * FROM join_requests ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();

        TwigService::refreshGlobals();
        echo TwigService::view()->render('join_requests/index.twig', [
            'rows' => $rows,
            '_csrf' => Csrf::token(),
        ]);
    }

    public function show(int $id): void
    {
        Gate::allow(['admin']);

        $stmt = DB::pdo()->prepare("SELECT * FROM join_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch();

        if (!$req) {
            Session::flash('error', 'الطلب غير موجود.');
            header("Location: /admincp/join-requests");
            return;
        }

        TwigService::refreshGlobals();
        echo TwigService::view()->render('join_requests/show.twig', [
            'req' => $req,
            '_csrf' => Csrf::token(),
        ]);
    }

    public function confirmDelete(int $id): void
    {
        Gate::allow(['admin']);

        $stmt = DB::pdo()->prepare("SELECT * FROM join_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch();

        if (!$req) {
            Session::flash('error', 'الطلب غير موجود.');
            header("Location: /admincp/join-requests");
            return;
        }

        TwigService::refreshGlobals();
        echo TwigService::view()->render('join_requests/confirm_delete.twig', [
            'req' => $req,
            '_csrf' => Csrf::token(),
        ]);
    }

    public function delete(int $id): void
    {
        Gate::allow(['admin']);

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: /admincp/join-requests");
            return;
        }

        $stmt = DB::pdo()->prepare("DELETE FROM join_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);

        Session::flash('success', 'تم حذف الطلب بنجاح.');
        header("Location: /admincp/join-requests");
    }
}
