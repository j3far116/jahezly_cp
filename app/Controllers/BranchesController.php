<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Session;
use App\Core\Csrf;
use App\Services\Gate;
use App\Services\Scope;
use App\Models\Market;
use App\Models\Branch;
use App\Models\Location;

final class BranchesController
{
    /* ============================================================
       عرض فرع
       ============================================================ */
    public function show(int $market_id, int $id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = $bp . '/markets';
        $branches_base = "{$markets_base}/{$market_id}/branch";

        // صلاحيات النطاق
        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== $market_id) {
            Session::flash('error', 'غير مصرح لك بعرض هذا الفرع.');
            header("Location: {$markets_base}");
            return;
        }

        $market = Market::findById($market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        $branch = Branch::findWithLocation($id);
        if (!$branch || (int)$branch['market_id'] !== $market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        TwigService::refreshGlobals();
        echo TwigService::view()->render('branches/show.twig', [
            'market'           => ['id'=>$market['id'], 'name'=>$market['name']],
            'branch'           => $branch,
            'markets_base'     => $markets_base,
            'branches_base'    => $branches_base,
            'scoped_market_id' => $scopedId,
            '_csrf'            => Csrf::token(),
        ]);
    }

    /* ============================================================
       إنشاء فرع جديد
       ============================================================ */
    public function create(int $market_id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = "{$bp}/markets";
        $branches_base = "{$markets_base}/{$market_id}/branch";

        // التحقق من النطاق
        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== $market_id) {
            Session::flash('error', 'غير مصرح لك بإضافة فرع لهذا المتجر.');
            header("Location: {$markets_base}");
            return;
        }

        $market = Market::findById($market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        $locations = Location::listActive();

        TwigService::refreshGlobals();
        echo TwigService::view()->render('branches/create.twig', [
            'market'           => $market,
            'markets_base'     => $markets_base,
            'branches_base'    => $branches_base,
            'locations'        => $locations,
            'values'           => [
                'name'        => '',
                'location_id' => '',
                'status'      => 'inactive',
                'address'     => '',
            ],
            'errors' => [],
            '_csrf'  => Csrf::token(),
        ]);
    }

    /* ============================================================
       حفظ فرع جديد
       ============================================================ */
    public function store(int $market_id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = "{$bp}/markets";
        $branches_base = "{$markets_base}/{$market_id}/branch";

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: {$branches_base}/create");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== $market_id) {
            Session::flash('error', 'غير مصرح لك بإضافة فرع.');
            header("Location: {$markets_base}");
            return;
        }

        $market = Market::findById($market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        // ❌ النوع لم يعد موجوداً
        $values = [
            'name'        => trim($_POST['name'] ?? ''),
            'location_id' => trim($_POST['location_id'] ?? ''),
            'status'      => $_POST['status'] ?? 'inactive',
            'address'     => trim($_POST['address'] ?? ''),
        ];

        $errors = $this->validate($values);

        if ($errors) {
            TwigService::refreshGlobals();
            echo TwigService::view()->render('branches/create.twig', [
                'market'        => $market,
                'markets_base'  => $markets_base,
                'branches_base' => $branches_base,
                'locations'     => Location::listActive(),
                'values'        => $values,
                'errors'        => $errors,
                '_csrf'         => Csrf::token(),
            ]);
            return;
        }

        Branch::create($market_id, $values);

        Session::flash('success', 'تم إنشاء الفرع بنجاح.');
        header("Location: {$markets_base}/{$market_id}");
    }

    /* ============================================================
       تعديل فرع
       ============================================================ */
    public function edit(int $market_id, int $id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = "{$bp}/markets";
        $branches_base = "{$markets_base}/{$market_id}/branch";

        $market = Market::findById($market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        $branch = Branch::findById($id);
        if (!$branch || (int)$branch['market_id'] !== $market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== $market_id) {
            Session::flash('error', 'غير مصرح لك بتعديل هذا الفرع.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $locations = Location::listActive();

        TwigService::refreshGlobals();
        echo TwigService::view()->render('branches/edit.twig', [
            'market'        => $market,
            'id'            => $id,
            'markets_base'  => $markets_base,
            'branches_base' => $branches_base,
            'locations'     => $locations,
            'values'        => [
                'name'        => $branch['name'],
                'location_id' => $branch['location_id'],
                'status'      => $branch['status'],
                'address'     => $branch['address'],
            ],
            'errors' => [],
            '_csrf'  => Csrf::token(),
        ]);
    }

    /* ============================================================
       تحديث فرع
       ============================================================ */
    public function update(int $market_id, int $id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = "{$bp}/markets";
        $branches_base = "{$markets_base}/{$market_id}/branch";

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: {$branches_base}/{$id}/edit");
            return;
        }

        $branch = Branch::findById($id);
        if (!$branch || (int)$branch['market_id'] !== $market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== $market_id) {
            Session::flash('error', 'غير مصرح لك بتعديل هذا الفرع.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $values = [
            'name'        => trim($_POST['name'] ?? ''),
            'location_id' => trim($_POST['location_id'] ?? ''),
            'status'      => $_POST['status'] ?? 'inactive',
            'address'     => trim($_POST['address'] ?? ''),
        ];

        $errors = $this->validate($values);

        if ($errors) {
            TwigService::refreshGlobals();
            echo TwigService::view()->render('branches/edit.twig', [
                'market'        => Market::findById($market_id),
                'id'            => $id,
                'markets_base'  => $markets_base,
                'branches_base' => $branches_base,
                'locations'     => Location::listActive(),
                'values'        => $values,
                'errors'        => $errors,
                '_csrf'         => Csrf::token(),
            ]);
            return;
        }

        Branch::updateById($id, $values);

        Session::flash('success', 'تم تحديث بيانات الفرع.');
        header("Location: {$branches_base}/{$id}");
    }

    /* ============================================================
       حذف فرع
       ============================================================ */
    public function delete(int $market_id, int $id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = "{$bp}/markets";
        $branches_base = "{$markets_base}/{$market_id}/branch";

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: {$branches_base}/{$id}");
            return;
        }

        $branch = Branch::findById($id);
        if (!$branch || (int)$branch['market_id'] !== $market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== $market_id) {
            Session::flash('error', 'غير مصرح لك بحذف هذا الفرع.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        Branch::deleteById($id);

        Session::flash('success', 'تم حذف الفرع بنجاح.');
        header("Location: {$markets_base}/{$market_id}");
    }

    /* ============================================================
       تحقق مدخلات — بدون النوع
       ============================================================ */
    private function validate(array $v): array
    {
        $errors = [];

        if ($v['name'] === '' || mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 150) {
            $errors['name'] = 'الاسم مطلوب (2–150 حرف).';
        }

        // ليس هناك type بعد الآن

        if ($v['location_id'] === '' || !ctype_digit($v['location_id']) || !Location::existsActive((int)$v['location_id'])) {
            $errors['location_id'] = 'اختر موقعًا صالحًا.';
        }

        if (!in_array($v['status'], ['active','inactive'], true)) {
            $errors['status'] = 'الحالة غير صحيحة.';
        }

        if (mb_strlen($v['address']) > 255) {
            $errors['address'] = 'العنوان أطول من 255 حرفًا.';
        }

        return $errors;
    }
}
