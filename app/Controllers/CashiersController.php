<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Session;
use App\Core\Csrf;
use App\Services\Gate;
use App\Services\Scope;
use App\Models\Market;
use App\Models\Cashier;

final class CashiersController
{

    public function index(int $marketId): void
{
    \App\Services\Gate::allow(['admin','owner']);

    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

    // تحقق نطاق التاجر
    $scoped = \App\Services\Scope::marketIdForCurrentUser();
    if ($scoped !== null && $scoped !== (int)$marketId) {
        \App\Core\Session::flash('error','غير مصرح لك بعرض هذا المتجر.');
        header('Location: ' . $bp . '/dashboard'); return;
    }

    // جلب المتجر
    $market = \App\Models\Market::findById((int)$marketId);
    if (!$market) {
        \App\Core\Session::flash('error','المتجر غير موجود.');
        header('Location: ' . $bp . '/markets'); return;
    }

    // جلب مستخدمي المتجر (بدون أي فلترة)
    $rows = \App\Models\Cashier::listForMarket((int)$marketId);

    // خريطة الفروع للأسماء
    $branches = \App\Core\DB::pdo()
        ->query("SELECT id, name FROM branches WHERE market_id = ".(int)$marketId." ORDER BY id DESC")
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $branchMap = [];
    foreach ($branches as $b) $branchMap[(string)$b['id']] = $b['name'];

    // تمرير البيانات إلى الواجهة
    \App\Core\TwigService::refreshGlobals();
    echo \App\Core\TwigService::view()->render('cashiers/index.twig', [
        'base'      => $base,
        'market'    => $market,
        'rows'      => $rows,
        'branchMap' => $branchMap,
        '_csrf'     => \App\Core\Csrf::token(),
    ]);
}


// == [ADD] شاشة عامة: كل الكاشيرات في جميع المتاجر
public function adminIndex(): void
{
    \App\Services\Gate::allow(['admin']); // مدير عام فقط

    $bp    = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base  = $bp . '/cashiers';
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $pp    = max(1, (int)($_GET['pp'] ?? 20));   // per-page
    $off   = ($page - 1) * $pp;
    $q     = trim((string)($_GET['q'] ?? ''));

    // يتطلب إضافة listAll في الموديل
    [$rows, $total] = \App\Models\Cashier::listAll($pp, $off, $q);
    $totalPages     = (int)max(1, ceil($total / $pp));

    \App\Core\TwigService::refreshGlobals();
    echo \App\Core\TwigService::view()->render('cashiers/admin_index.twig', [
        'base'       => $base,
        'rows'       => $rows,
        'q'          => $q,
        'page'       => $page,
        'pp'         => $pp,
        'total'      => $total,
        'totalPages' => $totalPages,
        '_csrf'      => \App\Core\Csrf::token(),
    ]);
}


    public function create(int $marketId): void
    {
        Gate::allow(['admin','owner']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

        $scoped = Scope::marketIdForCurrentUser();
        if ($scoped !== null && $scoped !== (int)$marketId) { Session::flash('error','غير مصرح لك.'); header('Location: '.$bp.'/dashboard'); return; }

        $market = Market::findById((int)$marketId);
        if (!$market) { Session::flash('error','المتجر غير موجود.'); header('Location: '.$bp.'/markets'); return; }

        $branches = \App\Core\DB::pdo()
            ->query("SELECT id, name FROM branches WHERE market_id = ".(int)$marketId." ORDER BY id DESC")
            ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        TwigService::refreshGlobals();
        echo TwigService::view()->render('cashiers/create.twig', [
            'base'     => $base,
            'market'   => $market,
            'branches' => $branches,
            'values'   => ['name'=>'','username'=>'','pin'=>'','confirm_pin'=>'','role'=>'cashier','status'=>'active','branch_ids'=>[]],
            'errors'   => [],
            '_csrf'    => Csrf::token(),
        ]);
    }

    public function store(int $marketId): void
    {
        Gate::allow(['admin','owner']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

        $scoped = Scope::marketIdForCurrentUser();
        if ($scoped !== null && $scoped !== (int)$marketId) { Session::flash('error','غير مصرح لك.'); header('Location: '.$bp.'/dashboard'); return; }

        if (!Csrf::check($_POST['_csrf'] ?? null)) { Session::flash('error','طلب غير صالح.'); header('Location: '.$base.'/create'); return; }

$v = [
  'name'        => trim((string)($_POST['name'] ?? '')),
  'username'    => trim((string)($_POST['username'] ?? '')),
  'pin'         => trim((string)($_POST['pin'] ?? '')),
  'confirm_pin' => trim((string)($_POST['confirm_pin'] ?? '')),
  'role'        => (string)($_POST['role'] ?? 'cashier'),
  'status'      => (string)($_POST['status'] ?? 'active'),
  'branch_ids'  => (array)($_POST['branch_ids'] ?? []),
];

        $errors = [];
if ($v['name'] === '' || mb_strlen($v['name']) < 2) $errors['name'] = 'الاسم مطلوب.';
$un = \App\Models\Cashier::sanitizeUsername($v['username']);
if ($un === '') $errors['username'] = 'username يجب أن يكون 3–20 (حروف/أرقام/_ فقط).';
elseif (!\App\Models\Cashier::isUsernameUnique((int)$marketId, $un)) $errors['username'] = 'username مستخدم مسبقًا في هذا المتجر.';
if (!\App\Models\Cashier::isValidPin($v['pin'])) $errors['pin'] = 'PIN يجب أن يكون 4–8 أرقام.';
if ($v['pin'] !== $v['confirm_pin']) $errors['confirm_pin'] = 'تأكيد PIN غير مطابق.';
        if (!in_array($v['role'], ['owner','cashier'], true)) $errors['role'] = 'الدور غير صالح.';
        if (!in_array($v['status'], ['active','suspended','removed'], true)) $errors['status'] = 'الحالة غير صالحة.';

        if ($errors) {
            $branches = \App\Core\DB::pdo()
                ->query("SELECT id, name FROM branches WHERE market_id = ".(int)$marketId." ORDER BY id DESC")
                ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            TwigService::refreshGlobals();
            echo TwigService::view()->render('cashiers/create.twig', [
                'base'     => $base,
                'market'   => \App\Models\Market::findById((int)$marketId),
                'branches' => $branches,
                'values'   => $v,
                'errors'   => $errors,
                '_csrf'    => Csrf::token(),
            ]);
            return;
        }

        Cashier::create((int)$marketId, $v['name'], $un, $v['pin'], $v['role'], $v['status'], $v['branch_ids']);
        Session::flash('success','تم إضافة مستخدم المتجر بنجاح.');
        header('Location: ' . $base);
    }

    public function edit(int $marketId, int $muId): void
    {
        Gate::allow(['admin','owner']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

        $scoped = Scope::marketIdForCurrentUser();
        if ($scoped !== null && $scoped !== (int)$marketId) { Session::flash('error','غير مصرح لك.'); header('Location: '.$bp.'/dashboard'); return; }

        if (!Cashier::belongsToMarket($muId, (int)$marketId)) { Session::flash('error','التعيين غير موجود لهذا المتجر.'); header('Location: '.$base); return; }

        $assign = Cashier::getById($muId);
        $market = Market::findById((int)$marketId);

        $branches = \App\Core\DB::pdo()
            ->query("SELECT id, name FROM branches WHERE market_id = ".(int)$marketId." ORDER BY id DESC")
            ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        TwigService::refreshGlobals();
        echo TwigService::view()->render('cashiers/edit.twig', [
            'base'     => $base,
            'market'   => $market,
            'assign'   => $assign,
            'branches' => $branches,
            '_csrf'    => Csrf::token(),
        ]);
    }

    public function update(int $marketId, int $muId): void
    {
        Gate::allow(['admin','owner']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

        if (!Csrf::check($_POST['_csrf'] ?? null)) { Session::flash('error','طلب غير صالح.'); header('Location: '.$base); return; }
        if (!Cashier::belongsToMarket($muId, (int)$marketId)) { Session::flash('error','التعيين غير موجود لهذا المتجر.'); header('Location: '.$base); return; }

        $name    = trim((string)($_POST['name'] ?? ''));
        $codeRaw = trim((string)($_POST['username'] ?? ''));
        $role    = (string)($_POST['role'] ?? 'cashier');
        $status  = (string)($_POST['status'] ?? 'active');
        $branchIds = (array)($_POST['branch_ids'] ?? []);
        $newPin  = trim((string)($_POST['new_pin'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) < 2) $errors['name'] = 'الاسم مطلوب.';
        $un = Cashier::sanitizeUsername($codeRaw);
        if ($un === '') $errors['username'] = 'username غير صالح.';
        if ($newPin !== '' && !Cashier::isValidPin($newPin)) $errors['new_pin'] = 'PIN يجب أن يكون 4-8 أرقام.';
        if (!in_array($role, ['owner','cashier'], true)) $errors['role'] = 'الدور غير صالح.';
        if (!in_array($status, ['active','suspended','removed'], true)) $errors['status'] = 'الحالة غير صالحة.';

        if ($errors) {
            Session::flash('error','تحقق من الحقول.');
            header('Location: ' . $base . '/' . (int)$muId . '/edit'); return;
        }

        $ok = Cashier::updateById($muId, $name, $un, $role, $status, $branchIds, $newPin ?: null);
        if (!$ok) {
            Session::flash('error','تعذر حفظ التغييرات (قد يكون username مستخدمًا).');
            header('Location: ' . $base . '/' . (int)$muId . '/edit'); return;
        }

        Session::flash('success','تم حفظ التغييرات.');
        header('Location: ' . $base);
    }

    public function setStatus(int $marketId, int $muId): void
    {
        Gate::allow(['admin','owner']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

        if (!Csrf::check($_POST['_csrf'] ?? null)) { Session::flash('error','طلب غير صالح.'); header('Location: '.$base); return; }
        if (!Cashier::belongsToMarket($muId, (int)$marketId)) { Session::flash('error','التعيين غير موجود لهذا المتجر.'); header('Location: '.$base); return; }

        $status = (string)($_POST['status'] ?? '');
        if (!Cashier::setStatusById($muId, $status)) {
            Session::flash('error','تعذر تغيير الحالة.');
        } else {
            Session::flash('success','تم تغيير الحالة.');
        }
        header('Location: ' . $base);
    }

    public function destroy(int $marketId, int $muId): void
    {
        Gate::allow(['admin','owner']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

        if (!Csrf::check($_POST['_csrf'] ?? null)) { Session::flash('error','طلب غير صالح.'); header('Location: '.$base); return; }
        if (!Cashier::belongsToMarket($muId, (int)$marketId)) { Session::flash('error','التعيين غير موجود لهذا المتجر.'); header('Location: '.$base); return; }

        if (!Cashier::deleteById($muId)) {
            Session::flash('error','تعذر إلغاء الربط.');
        } else {
            Session::flash('success','تم حذف مستخدم المتجر.');
        }
        header('Location: ' . $base);
    }


    public function confirmDelete(int $marketId, int $muId): void
{
    Gate::allow(['admin','owner']);

    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/markets/' . (int)$marketId . '/cashiers';

    // تحقق نطاق المستخدم
    $scoped = Scope::marketIdForCurrentUser();
    if ($scoped !== null && $scoped !== (int)$marketId) {
        Session::flash('error','غير مصرح لك.');
        header('Location: '.$bp.'/dashboard'); return;
    }

    // يجب أن ينتمي الكاشير للمتجر
    if (!Cashier::belongsToMarket($muId, (int)$marketId)) {
        Session::flash('error','السجل غير موجود لهذا المتجر.');
        header('Location: '.$base); return;
    }

    $market = Market::findById((int)$marketId);
    $row    = Cashier::getById($muId);

    TwigService::refreshGlobals();
    echo TwigService::view()->render('cashiers/confirm_delete.twig', [
        'base'   => $base,           // مثال: /admincp/markets/9/cashiers
        'market' => $market,
        'row'    => $row,
        '_csrf'  => Csrf::token(),
    ]);
}


}
