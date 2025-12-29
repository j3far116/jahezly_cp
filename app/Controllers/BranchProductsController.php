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
use App\Models\Product;
use App\Models\BranchProduct;

final class BranchProductsController
{
    /** عرض جميع منتجات الفرع */
    public function index(int $market_id, int $branch_id): void
    {
        Gate::allow(['admin', 'owner']);

        $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $markets_base  = "{$bp}/markets";
        $branches_base = "{$markets_base}/{$market_id}/branch";
        $products_base = "{$branches_base}/{$branch_id}/products";

        // صلاحيات النطاق
        $scopedId = Scope::marketIdForCurrentUser();
        if ($scopedId !== null && $scopedId !== (int)$market_id) {
            Session::flash('error', 'غير مصرح لك بعرض منتجات هذا الفرع.');
            header("Location: {$markets_base}");
            return;
        }

        $market = Market::findById($market_id);
        $branch = Branch::findById($branch_id);
        if (!$market || !$branch || $branch['market_id'] !== $market['id']) {
            Session::flash('error', 'الفرع غير موجود.');
            header("Location: {$markets_base}/{$market_id}");
            return;
        }

        $products = Product::withBranchOverrides($market_id, $branch_id);

        TwigService::refreshGlobals();
        echo TwigService::view()->render('branches/products.twig', [
            'market'        => $market,
            'branch'        => $branch,
            'products'      => $products,
            'markets_base'  => $markets_base,
            'branches_base' => $branches_base,
            'products_base' => $products_base,
            'scoped_market_id' => $scopedId,
            '_csrf'         => Csrf::token(),
        ]);
    }

    /** عرض نموذج تعديل التخصيص */
public function edit(int $market_id, int $branch_id, int $product_id): void
{
    Gate::allow(['admin', 'owner']);

    $bp            = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $markets_base  = $bp . '/markets';
    $branches_base = $markets_base . '/' . $market_id . '/branch';

    // 🔹 التحقق من صلاحية الوصول
    $scopedId = \App\Services\Scope::marketIdForCurrentUser();
    if ($scopedId !== null && $scopedId !== (int)$market_id) {
        Session::flash('error', 'غير مصرح لك بعرض هذا الفرع.');
        header("Location: {$markets_base}");
        return;
    }

    // 🔹 التحقق من وجود المنتج
    $product = \App\Models\Product::find($product_id);
    if (!$product) {
        Session::flash('error', 'المنتج غير موجود.');
        header("Location: {$branches_base}/{$branch_id}/products");
        return;
    }

    // 🔹 التحقق من وجود الفرع
    $branch = \App\Models\Branch::findById($branch_id);
    if (!$branch || (int)$branch['market_id'] !== (int)$market_id) {
        Session::flash('error', 'الفرع غير موجود.');
        header("Location: {$markets_base}/{$market_id}");
        return;
    }

    // 🔹 جلب بيانات التخصيص (branch_products)
    $override = \App\Models\BranchProduct::findByBranchProduct($branch_id, $product_id);

    // إذا لم يوجد تخصيص مسبق، نجهز قيمًا افتراضية لتسهيل العرض في النموذج
    if (!$override) {
        $override = [
            'price'  => null,
            'name'   => null,
            'desc'   => null,
            'status' => 'inactive', // القيمة الافتراضية الجديدة
        ];
    }

    // 🔹 عرض صفحة التحرير
    \App\Core\TwigService::refreshGlobals();
    echo \App\Core\TwigService::view()->render('branches/product_edit.twig', [
        'market_id'   => $market_id,
        'branch_id'   => $branch_id,
        'product'     => $product,
        'override'    => $override,
        'branch'      => $branch,
        'markets_base'=> $markets_base,
        'branches_base'=> $branches_base,
        '_csrf'       => \App\Core\Csrf::token(),
    ]);
}


    /** حفظ التخصيص */
    public function save(int $market_id, int $branch_id, int $product_id): void
    {
        Gate::allow(['admin', 'owner']);

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: /admincp/markets/{$market_id}/branch/{$branch_id}/products/{$product_id}/edit");
            return;
        }

        $data = [
    'price'  => $_POST['price'] ?? null,
    'name'   => trim($_POST['name'] ?? ''),
    'desc'   => trim($_POST['desc'] ?? ''),
    'status' => $_POST['status'] ?? 'inactive', // ✅
];

        BranchProduct::saveOrUpdate($branch_id, $product_id, $data);

        Session::flash('success', 'تم حفظ التخصيص بنجاح.');
        header("Location: /admincp/markets/{$market_id}/branch/{$branch_id}/products");
    }

    /** إلغاء التخصيص */
    public function delete(int $market_id, int $branch_id, int $product_id): void
    {
        Gate::allow(['admin', 'owner']);

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'طلب غير صالح.');
            header("Location: /admincp/markets/{$market_id}/branch/{$branch_id}/products");
            return;
        }

        BranchProduct::deleteOverride($branch_id, $product_id);

        Session::flash('info', 'تم إلغاء تخصيص المنتج.');
        header("Location: /admincp/markets/{$market_id}/branch/{$branch_id}/products");
    }
}
