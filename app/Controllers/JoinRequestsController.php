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
    /**
     * قائمة طلبات الانضمام
     */
    public function index(): void
    {
        Gate::allow(['admin']);

        $stmt = DB::pdo()->query("
            SELECT
                id,
                business_name,
                owner_or_authorized,
                commercial_record,
                phone,
                email,
                city,
                activity,
                branches_count,
                lat,
                lng,
                notes,
                created_at
            FROM join_requests
            ORDER BY created_at DESC
        ");

        $rows = $stmt->fetchAll();

        // تجهيز رابط الخريطة
        foreach ($rows as &$r) {
            $r['map_url'] = (!empty($r['lat']) && !empty($r['lng']))
                ? 'https://maps.google.com/?q=' . $r['lat'] . ',' . $r['lng']
                : null;
        }
        unset($r);

        TwigService::refreshGlobals();
        echo TwigService::view()->render('join_requests/index.twig', [
            'rows'  => $rows,
            '_csrf' => Csrf::token(),
        ]);
    }

    /**
     * عرض طلب واحد
     */
    public function show(int $id): void
    {
        Gate::allow(['admin']);

        $stmt = DB::pdo()->prepare("
            SELECT
                id,
                business_name,
                owner_or_authorized,
                commercial_record,
                phone,
                email,
                city,
                activity,
                branches_count,
                lat,
                lng,
                notes,
                created_at
            FROM join_requests
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch();

        if (!$req) {
            Session::flash('error', 'الطلب غير موجود.');
            header('Location: /admincp/join-requests');
            return;
        }

        $req['map_url'] = (!empty($req['lat']) && !empty($req['lng']))
            ? 'https://maps.google.com/?q=' . $req['lat'] . ',' . $req['lng']
            : null;

        TwigService::refreshGlobals();
        echo TwigService::view()->render('join_requests/show.twig', [
            'req'   => $req,
            '_csrf' => Csrf::token(),
        ]);
    }

    /**
     * تأكيد الحذف
     */
    public function confirmDelete(int $id): void
    {
        Gate::allow(['admin']);

        $stmt = DB::pdo()->prepare("
            SELECT
                id,
                business_name,
                owner_or_authorized
            FROM join_requests
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch();

        if (!$req) {
            Session::flash('error', 'الطلب غير موجود.');
            header('Location: /admincp/join-requests');
            return;
        }

        TwigService::refreshGlobals();
        echo TwigService::view()->render('join_requests/confirm_delete.twig', [
            'req'   => $req,
            '_csrf' => Csrf::token(),
        ]);
    }

    /**
     * حذف الطلب
     */
    public function delete(int $id): void
    {
        Gate::allow(['admin']);

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header('Location: /admincp/join-requests');
            return;
        }

        $stmt = DB::pdo()->prepare("DELETE FROM join_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);

        Session::flash('success', 'تم حذف الطلب بنجاح.');
        header('Location: /admincp/join-requests');
    }
}
