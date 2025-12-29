<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Models\Product;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Market;
use App\Services\Scope;

final class OptionGroupsController
{
    private function pullFlash(string $key, $default = null)
    {
        $k = '_flash_' . $key;
        $val = $_SESSION[$k] ?? $default;
        if (array_key_exists($k, $_SESSION)) unset($_SESSION[$k]);
        return $val;
    }

    private function putFlash(string $key, $value): void
    {
        $_SESSION['_flash_' . $key] = $value;
    }

    // ============================================================
    // INDEX: عرض القروبات الخاصة بمنتج
    // ============================================================
    public function index(int $market_id, int $product_id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $ownerMid = Scope::marketIdForCurrentUser();

        if ($ownerMid !== null && $ownerMid !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header("Location: {$admin}/markets/{$ownerMid}/products");
            exit;
        }

        $product = Product::find($product_id);
        $market  = Market::findById($market_id);

        if (!$product || !$market || (int)$product['market_id'] !== $market_id) {
            http_response_code(404);
            exit('Product or Market not found');
        }

        $groups = OptionGroup::allByProduct($product_id);

        foreach ($groups as &$g) {
            $decoded = json_decode($g['options'] ?? '[]', true);
            $g['options_count'] = is_array($decoded) ? count($decoded) : 0;
        }
        unset($g);

        echo TwigService::view()->render('option_groups/index.twig', [
            'market'  => $market,
            'product' => $product,
            'groups'  => $groups,
            'base'    => "{$admin}/markets/{$market_id}/products/{$product_id}/option-groups",
            'errors'  => $this->pullFlash('errors', []),
            'success' => $this->pullFlash('success'),
        ]);
    }

    public function create(int $market_id, int $product_id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        $ownerMid = \App\Services\Scope::marketIdForCurrentUser();
        if ($ownerMid !== null && $ownerMid !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header("Location: {$admin}/markets/{$ownerMid}/products");
            exit;
        }

        $product = \App\Models\Product::find($product_id);
        if (!$product || (int)$product['market_id'] !== $market_id) {
            http_response_code(404);
            exit('Product not found');
        }

        $market = \App\Models\Market::findById($market_id);
        if (!$market) {
            http_response_code(404);
            exit('Market not found');
        }

        echo \App\Core\TwigService::view()->render('option_groups/create.twig', [
            'market'           => $market,
            'product'          => $product,
            'base'             => "{$admin}/markets/{$market_id}/products/{$product_id}/option-groups",
            'errs'             => $this->pullFlash('errors', []),
            'old'              => $this->pullFlash('old', []),
            'scoped_market_id' => $ownerMid,
        ]);
    }


    public function store(int $market_id, int $product_id)
    {
        if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $backUrl = "{$admin}/markets/{$market_id}/products/{$product_id}/option-groups/create";

        $ownerMid = \App\Services\Scope::marketIdForCurrentUser();
        if ($ownerMid !== null && $ownerMid !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header("Location: {$admin}/markets/{$ownerMid}/products");
            exit;
        }

        $product = \App\Models\Product::find($product_id);
        if (!$product || (int)$product['market_id'] !== $market_id) {
            http_response_code(404);
            exit('Product not found');
        }

        // التحقق من البيانات
        $name      = trim($_POST['name'] ?? '');
        $type      = ($_POST['type'] ?? 'single') === 'multi' ? 'multi' : 'single';
        $required  = (int)($_POST['required'] ?? 0);
        $min       = (int)($_POST['min'] ?? 0);
        $max       = ($_POST['max'] ?? '') !== '' ? (int)$_POST['max'] : null;

        $errors = [];
        if ($name === '') $errors['name'] = 'اسم المجموعة مطلوب';
        if (mb_strlen($name) > 100) $errors['name'] = 'الاسم لا يجب أن يتجاوز 100 حرف';
        if ($min < 0) $errors['min'] = 'الحد الأدنى لا يمكن أن يكون سالبًا';
        if ($max !== null && $max < $min) $errors['max'] = 'الحد الأقصى يجب أن يكون أكبر من الحد الأدنى';

        if ($errors) {
            $this->putFlash('errors', $errors);
            $this->putFlash('old', $_POST);
            header("Location: {$backUrl}");
            exit;
        }

        // حفظ المجموعة
        $id = \App\Models\OptionGroup::create([
            'product_id' => $product_id,
            'name'       => $name,
            'type'       => $type,
            'required'   => $required,
            'min'        => $min,
            'max'        => $max,
            'options'    => json_encode([]),
        ]);

        $this->putFlash('success', '✅ تمت إضافة المجموعة بنجاح');
        header("Location: {$admin}/markets/{$market_id}/products/{$product_id}/option-groups");
        exit;
    }


    // ============================================================
    // CUSTOMIZE: تخصيص خيارات المجموعة
    // ============================================================
public function customize(int $market_id, int $product_id, int $group_id)
{
    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

    $group = OptionGroup::find($group_id);
    if (!$group || (int)$group['product_id'] !== $product_id) {
        http_response_code(404);
        exit('Option group not found');
    }

    $product = Product::find($product_id);
    $market  = Market::findById($market_id);
    if (!$product || !$market) {
        http_response_code(404);
        exit('Invalid product or market');
    }

    // ✅ جلب الخيارات الخاصة والعامة
    $productOptions = Option::allForProduct($product_id);
    $globalOptions  = Option::allForMarketGlobal($market_id);

    // ✅ تحليل الخيارات المضافة في هذه المجموعة
    $decoded = json_decode($group['options'] ?? '[]', true) ?: [];
    usort($decoded, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
    $selectedIds = array_column($decoded, 'id');

    // ✅ تحديد الخيارات المضافة فعليًا
    $selectedOptions = [];
    foreach ($selectedIds as $sid) {
        foreach (array_merge($productOptions, $globalOptions) as $opt) {
            if ((int)$opt->id === (int)$sid) {
                $selectedOptions[] = $opt;
                break;
            }
        }
    }

    // ✅ الخيارات المتاحة فقط (اللي لم تُضف بعد)
    $availableProductOptions = array_values(array_filter($productOptions, fn($o) => !in_array((int)$o->id, $selectedIds)));
    $availableGlobalOptions  = array_values(array_filter($globalOptions,  fn($o) => !in_array((int)$o->id, $selectedIds)));

    echo TwigService::view()->render('option_groups/customize.twig', [
        'market'            => $market,
        'product'           => $product,
        'group'             => $group,
        'selected_options'  => $selectedOptions,
        'product_options'   => $availableProductOptions,
        'global_options'    => $availableGlobalOptions,
        'base'              => "{$admin}/markets/{$market_id}/products/{$product_id}/option-groups",
    ]);
}



    // ============================================================
    // AJAX: إضافة خيار جديد للمنتج مباشرة من شاشة التخصيص
    // ============================================================
    /* public function ajaxAddProductOption(int $market_id, int $product_id, int $group_id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            echo json_encode(['ok' => false, 'msg' => 'CSRF']);
            return;
        }

        $name  = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);

        if ($name === '') {
            echo json_encode(['ok' => false, 'msg' => 'الاسم مطلوب']);
            return;
        }

        $product = Product::find($product_id);
        if (!$product || (int)$product['market_id'] !== $market_id) {
            echo json_encode(['ok' => false, 'msg' => 'المنتج غير موجود']);
            return;
        }

        $optionId = Option::create([
            'market_id'  => $market_id,
            'product_id' => $product_id,
            'name'       => $name,
            'price'      => number_format($price, 2, '.', ''),
            'available'  => 1,
            'sort_order' => 0,
        ]);

        echo json_encode([
            'ok'    => true,
            'msg'   => 'تمت إضافة الخيار بنجاح',
            'id'    => $optionId,
            'name'  => $name,
            'price' => $price,
        ]);
    } */

    public function ajaxAddOption(int $market_id, int $product_id, int $group_id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            echo json_encode(['ok' => false, 'msg' => 'CSRF']);
            return;
        }

        $optionId = (int)($_POST['option_id'] ?? 0);
        if ($optionId <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid option']);
            return;
        }

        $group = OptionGroup::find($group_id);
        if (!$group || (int)$group['product_id'] !== $product_id) {
            echo json_encode(['ok' => false, 'msg' => 'Group not found']);
            return;
        }

        $options = json_decode($group['options'] ?? '[]', true) ?: [];

        // إذا كانت الخيارات بصيغة ID فقط — نطبعها ككائنات بالترتيب
        $normalized = [];
        foreach ($options as $i => $o) {
            if (is_array($o)) {
                $normalized[] = ['id' => (int)$o['id'], 'sort_order' => (int)($o['sort_order'] ?? $i + 1)];
            } else {
                $normalized[] = ['id' => (int)$o, 'sort_order' => $i + 1];
            }
        }

        // منع التكرار
        foreach ($normalized as $n) {
            if ((int)$n['id'] === $optionId) {
                echo json_encode(['ok' => true, 'msg' => 'Already exists']);
                return;
            }
        }

        $normalized[] = ['id' => $optionId, 'sort_order' => count($normalized) + 1];
        OptionGroup::updateOptions($group_id, $normalized);

        echo json_encode(['ok' => true, 'msg' => 'تمت الإضافة بنجاح']);
    }

    public function ajaxRemoveOption(int $market_id, int $product_id, int $group_id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            echo json_encode(['ok' => false, 'msg' => 'CSRF']);
            return;
        }

        $optionId = (int)($_POST['option_id'] ?? 0);
        if ($optionId <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid option']);
            return;
        }

        $group = OptionGroup::find($group_id);
        if (!$group || (int)$group['product_id'] !== $product_id) {
            echo json_encode(['ok' => false, 'msg' => 'Group not found']);
            return;
        }

        $options = json_decode($group['options'] ?? '[]', true) ?: [];

        $normalized = [];
        foreach ($options as $i => $o) {
            if (is_array($o)) {
                $normalized[] = ['id' => (int)$o['id'], 'sort_order' => (int)($o['sort_order'] ?? $i + 1)];
            } else {
                $normalized[] = ['id' => (int)$o, 'sort_order' => $i + 1];
            }
        }

        // احذف الخيار المطلوب
        $filtered = array_values(array_filter($normalized, fn($x) => (int)$x['id'] !== $optionId));

        // إعادة ترتيب sort_order
        foreach ($filtered as $i => &$f) $f['sort_order'] = $i + 1;
        unset($f);

        OptionGroup::updateOptions($group_id, $filtered);

        echo json_encode(['ok' => true, 'msg' => 'تمت الإزالة بنجاح']);
    }


    public function ajaxSaveOrder(int $market_id, int $product_id, int $id)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!\App\Core\Csrf::check($token)) {
            echo json_encode(['ok' => false, 'msg' => 'CSRF']);
            return;
        }

        $group = \App\Models\OptionGroup::find($id);
        if (!$group || (int)$group['product_id'] !== $product_id) {
            echo json_encode(['ok' => false, 'msg' => 'Not found']);
            return;
        }

        // استلام البيانات من JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $order = $input['order'] ?? [];

        if (!is_array($order)) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid data']);
            return;
        }

        // تحويلها لصيغة التخزين المطلوبة
        $normalized = array_map(function ($item) {
            return [
                'id' => (int)$item['id'],
                'sort_order' => (int)$item['sort_order']
            ];
        }, $order);

        // حفظ الترتيب الجديد
        \App\Models\OptionGroup::updateOptions($id, $normalized);

        echo json_encode(['ok' => true]);
    }
    public static function updateOptions(int $id, array $options): bool
    {
        $pdo = \App\Core\DB::pdo();
        $stmt = $pdo->prepare("UPDATE option_groups SET options = :opts WHERE id = :id");
        return $stmt->execute([
            'opts' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'id'   => $id
        ]);
    }


    public function edit(int $market_id, int $product_id, int $id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        $ownerMid = \App\Services\Scope::marketIdForCurrentUser();
        if ($ownerMid !== null && $ownerMid !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header("Location: {$admin}/markets/{$ownerMid}/products");
            exit;
        }

        $group = \App\Models\OptionGroup::find($id);
        if (!$group || (int)$group['product_id'] !== $product_id) {
            http_response_code(404);
            exit('Option group not found');
        }

        $product = \App\Models\Product::find($product_id);
        $market  = \App\Models\Market::findById($market_id);

        echo \App\Core\TwigService::view()->render('option_groups/edit.twig', [
            'market'           => $market,
            'product'          => $product,
            'item'             => $group,
            'base'             => "{$admin}/markets/{$market_id}/products/{$product_id}/option-groups",
            'errs'             => $this->pullFlash('errors', []),
            'old'              => $this->pullFlash('old', []),
            'scoped_market_id' => $ownerMid,
        ]);
    }
    public function update(int $market_id, int $product_id, int $id)
    {
        if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $backUrl = "{$admin}/markets/{$market_id}/products/{$product_id}/option-groups/{$id}/edit";

        $ownerMid = \App\Services\Scope::marketIdForCurrentUser();
        if ($ownerMid !== null && $ownerMid !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header("Location: {$admin}/markets/{$ownerMid}/products");
            exit;
        }

        $group = \App\Models\OptionGroup::find($id);
        if (!$group || (int)$group['product_id'] !== $product_id) {
            http_response_code(404);
            exit('Option group not found');
        }

        $name      = trim($_POST['name'] ?? '');
        $type      = ($_POST['type'] ?? 'single') === 'multi' ? 'multi' : 'single';
        $required  = (int)($_POST['required'] ?? 0);
        $min       = (int)($_POST['min'] ?? 0);
        $max       = ($_POST['max'] ?? '') !== '' ? (int)$_POST['max'] : null;
        $available = isset($_POST['available']) ? 1 : 0;

        $errors = [];
        if ($name === '') $errors['name'] = 'اسم المجموعة مطلوب';
        if (mb_strlen($name) > 100) $errors['name'] = 'الاسم لا يجب أن يتجاوز 100 حرف';
        if ($min < 0) $errors['min'] = 'الحد الأدنى لا يمكن أن يكون سالبًا';
        if ($max !== null && $max < $min) $errors['max'] = 'الحد الأقصى يجب أن يكون أكبر من الحد الأدنى';

        if ($errors) {
            $this->putFlash('errors', $errors);
            $this->putFlash('old', $_POST);
            header("Location: {$backUrl}");
            exit;
        }

        // تحديث البيانات في قاعدة البيانات
        \App\Models\OptionGroup::updateById($id, [
            'name'      => $name,
            'type'      => $type,
            'required'  => $required,
            'min'       => $min,
            'max'       => $max,
            'available' => $available,
        ]);

        $this->putFlash('success', '✅ تم تحديث المجموعة بنجاح');
        header("Location: {$admin}/markets/{$market_id}/products/{$product_id}/option-groups");
        exit;
    }
    public function deleteConfirm(int $market_id, int $product_id, int $id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        $group = \App\Models\OptionGroup::find($id);
        if (!$group || (int)$group['product_id'] !== $product_id) {
            http_response_code(404);
            exit('Option group not found');
        }

        $product = \App\Models\Product::find($product_id);
        $market  = \App\Models\Market::findById($market_id);

        echo \App\Core\TwigService::view()->render('option_groups/confirm_delete.twig', [
            'market'  => $market,
            'product' => $product,
            'group'   => $group,
            'base'    => "{$admin}/markets/{$market_id}/products/{$product_id}/option-groups",
        ]);
    }
/**
 * تنفيذ عملية حذف مجموعة الخيارات
 */
public function destroy(int $market_id, int $product_id, int $id)
{
    if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('CSRF');
    }

    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
    $group = \App\Models\OptionGroup::find($id);

    // التحقق من أن المجموعة موجودة وتتبع المنتج الصحيح
    if ($group && (int)($group['product_id'] ?? 0) === (int)$product_id) {
        \App\Models\OptionGroup::deleteById($id);
        $this->putFlash('success', '🗑️ تم حذف مجموعة الخيارات بنجاح');
    } else {
        $this->putFlash('errors', ['notfound' => 'المجموعة غير موجودة أو لا تتبع هذا المنتج']);
    }

    header("Location: {$admin}/markets/{$market_id}/products/{$product_id}/option-groups");
    exit;
}

/**
 * إضافة خيار جديد خاص بالمنتج
 */
public function ajaxAddProductOption(int $market_id, int $product_id)
{
    header('Content-Type: application/json; charset=utf-8');

    if (!\App\Core\Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        echo json_encode(['ok' => false, 'msg' => 'CSRF']);
        return;
    }

    $name  = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);

    if ($name === '') {
        echo json_encode(['ok' => false, 'msg' => 'الاسم مطلوب']);
        return;
    }

    // ✅ إنشاء الخيار الجديد واستلام المعرف الجديد
    $id = \App\Models\Option::create([
    'market_id'  => $market_id,
    'product_id' => $product_id,
    'name'       => $name,
    'price'      => $price,
    'available'  => 1,
    'sort_order' => 0,
]);

echo json_encode([
    'ok'   => true,
    'msg'  => 'تمت الإضافة بنجاح',
    'item' => [
        'id'    => $id,
        'name'  => $name,
        'price' => $price
    ]
]);

}


/**
 * تعديل خيار خاص بالمنتج
 */
public function ajaxUpdateProductOption(int $market_id, int $product_id, int $id)
{
    header('Content-Type: application/json; charset=utf-8');
    if (!\App\Core\Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        echo json_encode(['ok' => false, 'msg' => 'CSRF']); return;
    }

    $option = \App\Models\Option::find($id);
    if (!$option || (int)$option['product_id'] !== $product_id) {
        echo json_encode(['ok' => false, 'msg' => 'الخيار غير موجود']); return;
    }

    $name  = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);

    \App\Models\Option::updateById($id, [
        'name'  => $name,
        'price' => $price,
    ]);

    echo json_encode(['ok' => true, 'msg' => 'تم التعديل بنجاح']);
}

/**
 * حذف خيار خاص بالمنتج
 */
public function ajaxDeleteProductOption(int $market_id, int $product_id, int $id)
{
    header('Content-Type: application/json; charset=utf-8');
    if (!\App\Core\Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        echo json_encode(['ok' => false, 'msg' => 'CSRF']); return;
    }

    $option = \App\Models\Option::find($id);
    if (!$option || (int)$option['product_id'] !== $product_id) {
        echo json_encode(['ok' => false, 'msg' => 'الخيار غير موجود']); return;
    }

    \App\Models\Option::deleteById($id);
    echo json_encode(['ok' => true, 'msg' => 'تم الحذف بنجاح']);
}



/**
 * إرجاع قائمة خيارات المنتج بصيغة JSON
 * ✅ تُعرض فقط الخيارات غير المضافة للمجموعة الحالية
 * ✅ تعمل مع المسار:
 * /admincp/markets/{market_id}/products/{product_id}/product-options/list
 */
public function ajaxListProductOptions(int $market_id, int $product_id)
{
    header('Content-Type: application/json; charset=utf-8');

    // ✅ التحقق من CSRF
    if (!\App\Core\Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        echo json_encode(['ok' => false, 'msg' => 'CSRF']);
        return;
    }

    // ✅ قراءة group_id من الـ query string
    $group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

    // ✅ التحقق من السوق والمنتج
    $market  = \App\Models\Market::findById($market_id);
    $product = \App\Models\Product::find($product_id);
    if (!$market || !$product) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
        return;
    }

    // ✅ جلب كل خيارات المنتج
    $productOptions = \App\Models\Option::allForProduct($product_id);

    // ✅ إذا تم تمرير group_id، نحذف الخيارات المضافة في هذه المجموعة
    if ($group_id > 0) {
        $group = \App\Models\OptionGroup::find($group_id);
        $selectedIds = [];

        if ($group && !empty($group['options'])) {
            $decoded = json_decode($group['options'], true) ?: [];
            foreach ($decoded as $opt) {
                if (is_array($opt) && isset($opt['id'])) {
                    $selectedIds[] = (int)$opt['id'];
                } elseif (is_numeric($opt)) {
                    $selectedIds[] = (int)$opt;
                }
            }
        }

        // 🔹 استبعاد الخيارات التي تمت إضافتها
        $productOptions = array_filter($productOptions, function ($opt) use ($selectedIds) {
            $id = is_array($opt) ? $opt['id'] : $opt->id;
            return !in_array((int)$id, $selectedIds);
        });

        // ✅ ترتيب تصاعدي حسب sort_order أو id
usort($productOptions, function($a, $b) {
    $aSort = isset($a->sort_order) ? (int)$a->sort_order : ((is_array($a) && isset($a['sort_order'])) ? (int)$a['sort_order'] : 0);
    $bSort = isset($b->sort_order) ? (int)$b->sort_order : ((is_array($b) && isset($b['sort_order'])) ? (int)$b['sort_order'] : 0);

    // fallback بالـ id إذا ما فيه sort_order
    if ($aSort === 0 && $bSort === 0) {
        $aId = is_array($a) ? (int)$a['id'] : (int)$a->id;
        $bId = is_array($b) ? (int)$b['id'] : (int)$b->id;
        return $aId <=> $bId;
    }

    return $aSort <=> $bSort;
});

    }

    // ✅ إرجاع النتيجة
    echo json_encode([
        'ok'    => true,
        'items' => array_values($productOptions)
    ]);
}


public function ajaxListProductOptionsForManage(int $market_id, int $product_id)
{
    header('Content-Type: application/json; charset=utf-8');

    // group_id يأتي من Query String
    $group_id = (int)($_GET['group_id'] ?? 0);

    // ✅ تحقق CSRF (نفس بقية الـ AJAX)
    if (!\App\Core\Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        echo json_encode(['ok' => false, 'msg' => 'CSRF']);
        return;
    }

    // ✅ جلب الكيانات
    $market  = \App\Models\Market::findById($market_id);
    $product = \App\Models\Product::find($product_id);
    $group   = $group_id > 0 ? \App\Models\OptionGroup::find($group_id) : null;

    if (!$market || !$product || !$group) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
        return;
    }

    // ✅ كل خيارات المنتج
    $productOptions = \App\Models\Option::allForProduct($product_id);

    // ✅ IDs المضافة في المجموعة
    $decoded = json_decode($group['options'] ?? '[]', true) ?: [];
    $selectedIds = [];
    foreach ($decoded as $item) {
        if (is_array($item) && isset($item['id'])) $selectedIds[] = (int)$item['id'];
        elseif (is_numeric($item)) $selectedIds[] = (int)$item;
    }

    // ✅ نبني المخرجات مع is_used + sort_order
    $items = [];
    foreach ($productOptions as $opt) {
        $id    = is_array($opt) ? $opt['id']         : $opt->id;
        $name  = is_array($opt) ? $opt['name']       : $opt->name;
        $price = is_array($opt) ? $opt['price']      : $opt->price;
        $sort  = is_array($opt) ? ($opt['sort_order'] ?? 0) : ($opt->sort_order ?? 0);

        $items[] = [
            'id'         => (int)$id,
            'name'       => $name,
            'price'      => (float)$price,
            'sort_order' => (int)$sort,
            'is_used'    => in_array((int)$id, $selectedIds, true),
        ];
    }

    // ✅ ترتيب تصاعدي وفق sort_order ثم id
    usort($items, function ($a, $b) {
        $aa = $a['sort_order'] ?: $a['id'];
        $bb = $b['sort_order'] ?: $b['id'];
        return $aa <=> $bb;
    });

    echo json_encode(['ok' => true, 'items' => $items]);
}



/**
 * إعادة جلب القوائم (المتاحة والمضافة) بدون إعادة تحميل الصفحة
 */
public function ajaxRefreshOptionsLists(int $market_id, int $product_id, int $group_id)
{
    header('Content-Type: application/json; charset=utf-8');

    $group = \App\Models\OptionGroup::find($group_id);
    if (!$group || (int)$group['product_id'] !== $product_id) {
        echo json_encode(['ok' => false, 'msg' => 'المجموعة غير موجودة']);
        return;
    }

    // نفس منطق صفحة customize
    $storeOptions = \App\Models\Option::allForMarketAndProduct($market_id, $product_id);
    $selectedIds  = [];

    $decoded = json_decode($group['options'] ?? '[]', true) ?: [];
    foreach ($decoded as $item) {
        if (is_array($item) && isset($item['id'])) $selectedIds[] = (int)$item['id'];
        elseif (is_numeric($item)) $selectedIds[] = (int)$item;
    }

    $selected = [];
    $available = [];
    foreach ($storeOptions as $opt) {
        $id = is_array($opt) ? $opt['id'] : $opt->id;
        $name = is_array($opt) ? $opt['name'] : $opt->name;
        $row = ['id' => (int)$id, 'name' => $name];

        if (in_array((int)$id, $selectedIds)) $selected[] = $row;
        else $available[] = $row;
    }

    echo json_encode(['ok' => true, 'available' => $available, 'selected' => $selected]);
}


public function ajaxRefreshSelectedOptions(int $market_id, int $product_id, int $group_id)
{
    header('Content-Type: application/json; charset=utf-8');

    if (!\App\Core\Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        echo json_encode(['ok' => false, 'msg' => 'CSRF']);
        return;
    }

    $group = \App\Models\OptionGroup::find($group_id);
    if (!$group || (int)$group['product_id'] !== $product_id) {
        echo json_encode(['ok' => false, 'msg' => 'Group not found']);
        return;
    }

    // الخيارات المضافة في هذه المجموعة
    $decoded = json_decode($group['options'] ?? '[]', true) ?: [];
    $ids = array_column($decoded, 'id');

    if (!$ids) {
        echo json_encode(['ok' => true, 'selected' => []]);
        return;
    }

    // جلب بيانات الخيارات المضافة
    $in = implode(',', array_fill(0, count($ids), '?'));
    $pdo = \App\Core\DB::pdo();
    $stmt = $pdo->prepare("SELECT id, name, price FROM options WHERE id IN ($in) ORDER BY FIELD(id, $in)");
    $stmt->execute([...$ids, ...$ids]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'selected' => $rows]);
}


}
