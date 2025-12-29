<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Session;
use App\Core\Csrf;
use App\Services\Gate;
use App\Models\Market;
use App\Models\Branch;
use App\Models\Perm;
use App\Models\BranchConfig;

final class BranchConfigController
{
    /**
     * صفحة واحدة لعرض إعدادات جميع الفروع التابعة لمتجر واحد
     */
    public function index(int $market_id): void
    {
        Gate::allow(['admin', 'owner']);

        // 1) التحقق من وجود المتجر
        $market = Market::findById($market_id);
        if (!$market) {
            Session::flash('error', 'المتجر غير موجود.');
            header("Location: /admincp/markets");
            return;
        }

        // 2) جلب الفروع التابعة لهذا المتجر
        $branches = Branch::listByMarketId($market_id);

        // 3) جلب تعريفات الإعدادات من جدول perms لنوع branches
        $perms = Perm::all('branches');

        // 4) تحديد نوع المستخدم (admin / owner)
        $userRole = $_SESSION['user']['role'] ?? 'owner';

        // 5) دمج الإعدادات مع القيم الفعلية لكل فرع
        foreach ($branches as &$b) {
            $branchId    = (int) $b['id'];
            $savedValues = BranchConfig::list($branchId);
            $b['configs'] = [];

            foreach ($perms as $p) {
                // إعدادات الخيارات (لـ select)
                $optionsArr = [];
                if (!empty($p['options'])) {
                    $decoded = json_decode($p['options'], true);
                    $optionsArr = is_array($decoded) ? $decoded : [];
                }

                // الفروع المحظورة لهذا الإعداد
                $blockedIds = [];
                if (!empty($p['blocked_IDs'])) {
                    $decodedBlocked = json_decode($p['blocked_IDs'], true);
                    $blockedIds = is_array($decodedBlocked) ? $decodedBlocked : [];
                }
                // تأكد أنها أرقام صحيحة
                $blockedIds = array_map('intval', $blockedIds);

                // هل هذا الفرع محظور؟
                $isBlockedForBranch = in_array($branchId, $blockedIds, true);

                // قاعدة المنع:
                // - إذا المستخدم admin => دائماً مسموح له التعديل
                // - إذا المستخدم owner والفرع محظور => غير مسموح
                // - غير ذلك => مسموح
                $editable = ($userRole === 'admin') ? true : !$isBlockedForBranch;

                $b['configs'][] = [
                    'key'         => $p['key'],
                    'val_type'    => $p['val_type'],
                    'desc'        => $p['desc'],
                    'options_arr' => $optionsArr,
                    'default'     => $p['value'],
                    'value'       => $savedValues[$p['key']] ?? null,
                    'editable'    => $editable,
                ];
            }
        }
        unset($b); // احتياطًا لفك المرجع

        // 6) عرض الصفحة
        TwigService::refreshGlobals();
        echo TwigService::view()->render('branchConfig/index.twig', [
            'market'   => $market,
            'branches' => $branches,
            '_csrf'    => Csrf::token(),
        ]);
    }

    /**
     * حفظ إعدادات جميع الفروع التابعة لمتجر واحد
     */
    public function saveAll(int $market_id): void
    {
        Gate::allow(['admin', 'owner']);
        Csrf::check($_POST['_csrf'] ?? null);

        $data = $_POST['cfg'] ?? [];

        // نوع المستخدم (admin يمكنه تجاوز المنع)
        $userRole = $_SESSION['user']['role'] ?? 'owner';

        // 1) تحميل جميع تعريفات الإعدادات من جدول perms (نوع branches)
        $perms = Perm::all('branches');

        $defaults     = [];
        $blockedRules = [];

        foreach ($perms as $p) {
            $key = $p['key'];

            // القيمة الافتراضية
            $defaults[$key] = $p['value'];

            // الفروع المحظورة لهذا الإعداد
            $blocked = [];
            if (!empty($p['blocked_IDs'])) {
                $decoded = json_decode($p['blocked_IDs'], true);
                $blocked = is_array($decoded) ? $decoded : [];
            }
            // تأكد أنها أرقام صحيحة
            $blockedRules[$key] = array_map('intval', $blocked);
        }

        // 2) تنفيذ الحفظ لكل فرع ولكل إعداد
        foreach ($data as $branchIdKey => $settings) {
            $branchId = (int) $branchIdKey;

            foreach ($settings as $key => $value) {

                // (أ) منع تعديل الفروع المحظورة لغير المدير العام
                $isBlockedForBranch = in_array(
                    $branchId,
                    $blockedRules[$key] ?? [],
                    true
                );

                if ($userRole !== 'admin' && $isBlockedForBranch) {
                    // صاحب المتجر أو غيره لا يمكنه تعديل هذا الإعداد لهذا الفرع
                    continue;
                }

                // (ب) القيمة الافتراضية لهذا الإعداد
                $default = $defaults[$key] ?? null;

                // (ج) إذا القيمة فارغة أو مساوية للافتراضي → نحذف السطر من branches_config
                if ($value === '' || $value === null || $value == $default) {
                    BranchConfig::delete($branchId, $key);
                    continue;
                }

                // (د) إذا القيمة مختلفة عن الافتراضي → نحفظها فقط
                BranchConfig::set($branchId, $key, $value);
            }
        }

        // 3) Redirect
        Session::flash('success', 'تم حفظ إعدادات الفروع بنجاح.');
        header("Location: /admincp/markets/$market_id/branch/config");
        exit;
    }


    public function resetOneConfig(int $market_id, int $branch_id, string $key): void
{
    Gate::allow(['admin', 'owner']);
    Csrf::check($_POST['_csrf'] ?? null);

    // 1) تحقق أن الفرع يتبع المتجر
    $branch = Branch::findById($branch_id);
    if (!$branch || $branch['market_id'] != $market_id) {
        Session::flash('error', 'الفرع غير موجود أو لا يتبع هذا المتجر.');
        header("Location: /admincp/markets/$market_id/branch/config");
        return;
    }

    // 2) تحقق أن الإعداد موجود في جدول perms (نوع branches)
    $perm = Perm::findByKey($key);
    if (!$perm || $perm['type'] !== 'branches') {
        Session::flash('error', 'الإعداد غير موجود.');
        header("Location: /admincp/markets/$market_id/branch/config");
        return;
    }

    // 3) حذف قيمة الإعداد من جدول الفرع
    BranchConfig::delete($branch_id, $key);

    // 4) رجوع للصفحة
    Session::flash('success', "تم إعادة الإعداد ($key) إلى قيمته الافتراضية.");
    header("Location: /admincp/markets/$market_id/branch/config");
    exit;
}

}
