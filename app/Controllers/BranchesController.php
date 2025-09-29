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
use App\Models\Location; // ⬅️ جديد

final class BranchesController
{
    public function show(int $market_id, int $id): void
    {
        Gate::allow(['admin','owner']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = $bp . '/markets';
        $branches_base = $markets_base . '/' . $market_id . '/branch';

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== (int)$market_id) {
            Session::flash('error', 'غير مصرح لك بعرض هذا الفرع.');
            header("Location: {$markets_base}");
            return;
        }

        $market = Market::findById((int)$market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        $branch = Branch::findWithLocation((int)$id);
        if (!$branch || (int)$branch['market_id'] !== (int)$market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        TwigService::refreshGlobals();
        echo TwigService::view()->render('branches/show.twig', [
            'market'           => ['id'=>$market['id'],'name'=>$market['name']],
            'branch'           => $branch,
            'markets_base'     => $markets_base,
            'branches_base'    => $branches_base,
            'scoped_market_id' => $scopedId,
            '_csrf'            => Csrf::token(),
        ]);
    }

    public function create(int $market_id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = $bp . '/markets';
        $branches_base = $markets_base . '/' . $market_id . '/branch';

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== (int)$market_id) {
            Session::flash('error', 'غير مصرح لك بإضافة فرع لهذا المتجر.');
            header("Location: {$markets_base}");
            return;
        }

        $market = Market::findById((int)$market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        $locations = Location::listActive(); // ⬅️ جلب المواقع

        TwigService::refreshGlobals();
        echo TwigService::view()->render('branches/create.twig', [
            'market'           => $market,
            'markets_base'     => $markets_base,
            'branches_base'    => $branches_base,
            'locations'        => $locations, // ⬅️ تمرير للقالب
            'values'           => ['name'=>'','type'=>'1','location_id'=>'','status'=>'inactive','address'=>''],
            'errors'           => [],
            '_csrf'            => Csrf::token(),
        ]);
    }

    public function store(int $market_id): void
    {
        Gate::allow(['admin']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = $bp . '/markets';
        $branches_base = $markets_base . '/' . $market_id . '/branch';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: {$branches_base}/create");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== (int)$market_id) {
            Session::flash('error', 'غير مصرح لك بإضافة فرع لهذا المتجر.');
            header("Location: {$markets_base}");
            return;
        }

        $market = Market::findById((int)$market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        $values = [
            'name'        => trim((string)($_POST['name'] ?? '')),
            'type'        => (string)($_POST['type'] ?? ''),          // ⬅️ سيليكت
            'location_id' => (string)($_POST['location_id'] ?? ''),   // ⬅️ سيليكت من locations
            'status'      => (string)($_POST['status'] ?? 'inactive'),
            'address'     => trim((string)($_POST['address'] ?? '')),
        ];
        $errors = $this->validate($values);

        if ($errors) {
            TwigService::refreshGlobals();
            echo TwigService::view()->render('branches/create.twig', [
                'market'           => $market,
                'markets_base'     => $markets_base,
                'branches_base'    => $branches_base,
                'locations'        => Location::listActive(), // ⬅️ تمرير عند إعادة العرض
                'values'           => $values,
                'errors'           => $errors,
                '_csrf'            => Csrf::token(),
            ]);
            return;
        }

        Branch::create((int)$market_id, $values);
        Session::flash('success', 'تم إنشاء الفرع بنجاح.');
        header("Location: {$markets_base}/{$market_id}");
    }

    public function edit(int $market_id, int $id): void
    {
        Gate::allow(['admin','owner']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = $bp . '/markets';
        $branches_base = $markets_base . '/' . $market_id . '/branch';

        $market = Market::findById((int)$market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: {$markets_base}");
            return;
        }

        $branch = Branch::findById((int)$id);
        if (!$branch || (int)$branch['market_id'] !== (int)$market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== (int)$market_id) {
            Session::flash('error', 'غير مصرح لك بتعديل هذا الفرع.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $locations = Location::listActive(); // ⬅️ جلب المواقع

        TwigService::refreshGlobals();
        echo TwigService::view()->render('branches/edit.twig', [
            'market'           => $market,
            'id'               => (int)$id,
            'markets_base'     => $markets_base,
            'branches_base'    => $branches_base,
            'locations'        => $locations, // ⬅️ تمرير للقالب
            'values'           => [
                'name'        => $branch['name'] ?? '',
                'type'        => (string)($branch['type'] ?? '1'),
                'location_id' => (string)($branch['location_id'] ?? ''),
                'status'      => $branch['status'] ?? 'inactive',
                'address'     => $branch['address'] ?? '',
            ],
            'errors'           => [],
            '_csrf'            => Csrf::token(),
        ]);
    }

    public function update(int $market_id, int $id): void
    {
        Gate::allow(['admin','owner']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = $bp . '/markets';
        $branches_base = $markets_base . '/' . $market_id . '/branch';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: {$branches_base}/{$id}/edit");
            return;
        }

        $branch = Branch::findById((int)$id);
        if (!$branch || (int)$branch['market_id'] !== (int)$market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== (int)$market_id) {
            Session::flash('error', 'غير مصرح لك بتعديل هذا الفرع.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $values = [
            'name'        => trim((string)($_POST['name'] ?? '')),
            'type'        => (string)($_POST['type'] ?? ''),
            'location_id' => (string)($_POST['location_id'] ?? ''),
            'status'      => (string)($_POST['status'] ?? 'inactive'),
            'address'     => trim((string)($_POST['address'] ?? '')),
        ];
        $errors = $this->validate($values);

        if ($errors) {
            TwigService::refreshGlobals();
            echo TwigService::view()->render('branches/edit.twig', [
                'market'           => Market::findById((int)$market_id),
                'id'               => (int)$id,
                'markets_base'     => $markets_base,
                'branches_base'    => $branches_base,
                'locations'        => Location::listActive(), // ⬅️ تمرير عند إعادة العرض
                'values'           => $values,
                'errors'           => $errors,
                '_csrf'            => Csrf::token(),
            ]);
            return;
        }

        Branch::updateById((int)$id, $values);
        Session::flash('success', 'تم تحديث بيانات الفرع.');
        header("Location: {$branches_base}/{$id}");
    }

    public function delete(int $market_id, int $id): void
    {
        Gate::allow(['admin','owner']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = $bp . '/markets';
        $branches_base = $markets_base . '/' . $market_id . '/branch';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: {$branches_base}/{$id}");
            return;
        }

        $branch = Branch::findById((int)$id);
        if (!$branch || (int)$branch['market_id'] !== (int)$market_id) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== (int)$market_id) {
            Session::flash('error', 'غير مصرح لك بحذف هذا الفرع.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        Branch::deleteById((int)$id);
        Session::flash('success', 'تم حذف الفرع بنجاح.');
        header("Location: {$markets_base}/{$market_id}");
    }

    /** تحقق مُحدّث لمدخلات الفرع */
    private function validate(array $v): array
    {
        $errors = [];

        // الاسم
        if ($v['name'] === '' || mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 150) {
            $errors['name'] = 'الاسم مطلوب (2–150 حرف).';
        }

        // النوع: سيليكت 1 أو 2
        if ($v['type'] === '' || !ctype_digit($v['type']) || !in_array((int)$v['type'], [1, 2], true)) {
            $errors['type'] = 'اختر النوع (المطاعم أو الكافيهات).';
        }

        // الموقع: يجب أن يكون رقمًا لموقع موجود ومفعّل
        if ($v['location_id'] === '' || !ctype_digit($v['location_id']) || !Location::existsActive((int)$v['location_id'])) {
            $errors['location_id'] = 'اختر موقعًا صالحًا.';
        }

        // الحالة
        if ($v['status'] !== 'active' && $v['status'] !== 'inactive') {
            $errors['status'] = 'الحالة غير صحيحة.';
        }

        // العنوان
        if (mb_strlen($v['address']) > 255) {
            $errors['address'] = 'العنوان أطول من 255 حرفًا.';
        }

        return $errors;
    }
}
