<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\TwigService;
use App\Models\Market;
use App\Models\Product;
use App\Services\Scope;

class ProductsController
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

    // app/Controllers/ProductsController.php (داخل index($market_id))
 public function index(int $market_id)
{
    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

    // 🔹 صلاحيات نطاق المالك (owner scope)
    $ownerMid = \App\Services\Scope::marketIdForCurrentUser();
    if ($ownerMid !== null && $ownerMid !== $market_id) {
        $this->putFlash('errors', ['auth' => 'غير مصرح']);
        header("Location: {$admin}/markets/{$ownerMid}/products");
        exit;
    }

    // 🔹 التحقق من وجود المتجر
    $market = \App\Models\Market::findById($market_id);
    if (!$market) {
        http_response_code(404);
        exit('Market not found');
    }

    // 🔹 جلب أقسام المنتجات (مرتبة)
    $cats = \App\Models\Cat::allByMarketType($market_id, 'products');
    $catsById = [];
    foreach ($cats as $c) {
        $catsById[(int)$c['id']] = $c;
    }

    // 🔹 هل توجد أقسام؟ (لإظهار رسالة وتعطيل زر إضافة منتج)
    $hasCats = count($cats) > 0;

    // 🔹 جلب المنتجات مرتبة
// 🔹 جلب المنتجات مرتبة (استثناء removed)
$pdo = \App\Core\DB::pdo();
$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE market_id = ?
      AND status != 'removed'
    ORDER BY name ASC, id ASC
");
$stmt->execute([$market_id]);
$products = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];


    // 🔹 تجميع المنتجات حسب القسم
    $byCat = [];
    $uncategorized = [];
    foreach ($products as $p) {
        $cid = (int)($p['cat_id'] ?? 0);
        if ($cid > 0 && isset($catsById[$cid])) {
            $byCat[$cid][] = $p;
        } else {
            $uncategorized[] = $p;
        }
    }

    // 🔹 عرض القالب
    echo \App\Core\TwigService::view()->render('products/index.twig', [
        'market'           => $market,
        'base'             => "{$admin}/markets/{$market_id}/products",
        'cats'             => $cats,
        'byCat'            => $byCat,
        'uncategorized'    => $uncategorized,
        'hasCats'          => $hasCats,                // ⬅️ متغير مهم لتعطيل زر الإضافة
        'scoped_market_id' => $ownerMid,
        'errs'             => $this->pullFlash('errors', []),
    ]);
}






    public function show(int $market_id, int $id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $owner = Scope::marketIdForCurrentUser();
        if ($owner !== null && $owner !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header('Location: ' . ($admin . "/markets/{$owner}/products"));
            exit;
        }
        $p = Product::find($id);
        if (!$p || (int)$p['market_id'] !== $market_id) {
            http_response_code(404);
            exit('Product not found');
        }

        echo TwigService::view()->render('products/show.twig', [
            'product'          => $p,
            'base'             => "{$admin}/markets/{$market_id}/products",
            'scoped_market_id' => $owner,
        ]);
    }

public function create(int $market_id)
{
    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

    // جلب أقسام المنتجات
    $cats = \App\Models\Cat::allByMarketType($market_id, 'products');

    // 🔍 تحقق: هل يوجد أقسام؟
    if (!$cats || count($cats) === 0) {

        $this->putFlash('errors', [
            'no_cats' => 'يجب إضافة قسم واحد على الأقل قبل إضافة منتج جديد.'
        ]);

        // 🔗 التوجيه إلى صفحة الأقسام
        $catsUrl = "{$admin}/markets/{$market_id}/cats/";

        header("Location: {$catsUrl}");
        exit;
    }

    echo \App\Core\TwigService::view()->render('products/create.twig', [
        'market_id'        => $market_id,
        'base'             => "{$admin}/markets/{$market_id}/products",
        'errs'             => $this->pullFlash('errors', []),
        'old'              => $this->pullFlash('old', []),
        'cats'             => $cats,
        'scoped_market_id' => \App\Services\Scope::marketIdForCurrentUser(),
    ]);
}



    public function edit(int $market_id, int $id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $p = Product::find($id);
        if (!$p || (int)$p['market_id'] !== $market_id) {
            http_response_code(404);
            exit('Not found');
        }
        $cats = \App\Models\Cat::allByMarketType($market_id, 'products');

        echo TwigService::view()->render('products/edit.twig', [
            'item'             => $p,
            'base'             => "{$admin}/markets/{$market_id}/products",
            'cats'             => $cats,                     // ✅ تمرير الأقسام للاختيار

            'errs'             => $this->pullFlash('errors', []),
            'old'              => $this->pullFlash('old', []),
            'scoped_market_id' => Scope::marketIdForCurrentUser(),
        ]);
    }

public function store(int $market_id)
{
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('CSRF');
    }

    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

    // 🔍 تحقق من وجود أقسام
    $cats = \App\Models\Cat::allByMarketType($market_id, 'products');
    if (!$cats || count($cats) === 0) {

        $this->putFlash('errors', [
            'no_cats' => 'لا يمكن إضافة منتج لأن المتجر لا يحتوي على أقسام منتجات.'
        ]);
        $this->putFlash('old', $_POST);

        // 🔗 التوجيه إلى صفحة الأقسام
        $catsUrl = "{$admin}/markets/{$market_id}/cats/";
        header("Location: {$catsUrl}");
        exit;
    }

    // متابعة إضافة المنتج
    $data = $this->validateOrBack($_POST, $market_id, "{$admin}/markets/{$market_id}/products/create");
    $id   = \App\Models\Product::create($data);

    // رسالة نجاح
    $this->putFlash('success', 'تمت إضافة المنتج');

    // 🔥 توجيه إلى صفحة المنتجات
    header("Location: {$admin}/markets/{$market_id}/products");
    exit;
}


    

    public function update(int $market_id, int $id)
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        $p = Product::find($id);
        if (!$p || (int)$p['market_id'] !== $market_id) {
            http_response_code(404);
            exit('Not found');
        }

        $data = $this->validateOrBack($_POST, $market_id, "{$admin}/markets/{$market_id}/products/{$id}/edit");
        Product::update($id, $data);
        $this->putFlash('success', 'تم تحديث المنتج');
        header('Location: ' . "{$admin}/markets/{$market_id}/products");
        exit;
    } 

    /* public function delete(int $market_id, int $id)
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        $p = Product::find($id);
        if ($p && (int)$p['market_id'] === $market_id) {
            Product::delete($id);
            $this->putFlash('success', 'تم حذف المنتج');
        }
        header('Location: ' . "{$admin}/markets/{$market_id}/products");
        exit;
    } */

    /* public function updateMedia(int $market_id, int $id)
    {
        header('Content-Type: application/json; charset=utf-8');



        // CSRF
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!\App\Core\Csrf::check($token)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'CSRF']);
            return;
        }

        // تحقق المنتج
        $p = \App\Models\Product::find($id);
        if (!$p || (int)$p['market_id'] !== $market_id) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'msg' => 'Not found']);
            return;
        }

        // ملف مطلوب
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'msg' => 'فشل الرفع']);
            return;
        }

        $f = $_FILES['file'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($f['tmp_name']);
        if (!isset($allowed[$mime])) {
            echo json_encode(['ok' => false, 'msg' => 'صيغة غير مسموحة']);
            return;
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'msg' => 'الحجم يتجاوز 5MB']);
            return;
        }

        // توليد اسم وحفظ الملف
        $ext = $allowed[$mime];
        $name = time() . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
        $uploadsRel = '/uploads';
$dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uploadsRel;
if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $path = $dir . '/' . $name;
        if (!move_uploaded_file($f['tmp_name'], $path)) {
            echo json_encode(['ok' => false, 'msg' => 'تعذر الحفظ']);
            return;
        }

        // تحديث السجل
        \App\Models\Product::updateCover($id, $name);

        echo json_encode([
    'ok'  => true,
    'url' => '/uploads/' . $name,
]);
    } */

public function updateMedia(int $market_id, int $id)
{
    header('Content-Type: application/json; charset=utf-8');

    // CSRF
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!\App\Core\Csrf::check($token)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'CSRF']);
        return;
    }

    // تحقق المنتج
    $p = \App\Models\Product::find($id);
    if (!$p || (int)$p['market_id'] !== $market_id) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Not found']);
        return;
    }

    // تحقق الملف
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'msg' => 'فشل الرفع']);
        return;
    }

    $f = $_FILES['file'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($f['tmp_name']);
    if (!isset($allowed[$mime])) {
        echo json_encode(['ok' => false, 'msg' => 'صيغة غير مسموحة']);
        return;
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'msg' => 'الحجم يتجاوز 5MB']);
        return;
    }

    // توليد اسم الملف
    $ext  = $allowed[$mime];
    $name = time() . '-' . bin2hex(random_bytes(5)) . '.' . $ext;

    // ✅ مجلد الرفع في جذر الموقع
    $uploadsRel = '/uploads';
    $dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uploadsRel;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    // ✅ مسار الحفظ الفعلي
    $path = $dir . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $path)) {
        echo json_encode(['ok' => false, 'msg' => 'تعذر الحفظ']);
        return;
    }

    // ✅ تحديث السجل
    \App\Models\Product::updateCover($id, $name);

    // ✅ توليد رابط مطلق للصورة (يناسب الموقع الفعلي)
    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $url  = $host . '/uploads/' . $name;

    echo json_encode([
        'ok'  => true,
        'url' => $url,
    ]);
}


    private function validateOrBack(array $in, int $market_id, string $backUrl): array
    {
        $errors = [];

        $name = trim($in['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) $errors['name'] = 'الاسم مطلوب وبحد أقصى 255 حرفًا';

        $price = trim((string)($in['price'] ?? ''));
        if ($price === '' || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $price)) $errors['price'] = 'السعر غير صالح';

        $status = $in['status'] ?? 'inactive';
        if (!in_array($status, ['active', 'inactive'], true)) $errors['status'] = 'حالة غير صالحة';

        $cat_id = (int)($in['cat_id'] ?? 0);
        if ($cat_id <= 0) $errors['cat_id'] = 'القسم مطلوب';

        if ($errors) {
            $this->putFlash('errors', $errors);
            $this->putFlash('old', $in);
            header('Location: ' . $backUrl);
            exit;
        }

        return [
            'market_id' => $market_id,
            'cat_id'    => $cat_id,
            'status'    => $status,
            'name'      => $name,
            'desc'      => trim($in['desc'] ?? ''),
            'price'     => number_format((float)$price, 2, '.', ''),
            // cover يحدّث عبر updateMedia لاحقًا
        ];
    }

    public function confirmDelete(int $market_id, int $id)
    {
        $admin  = $_SERVER['BASE_PATH'] ?? '/admincp';
        $product = \App\Models\Product::find($id);
        $market  = \App\Models\Market::findById($market_id);
        if (!$product || !$market || (int)$product['market_id'] !== $market_id) {
            http_response_code(404);
            exit('Product not found');
        }

        echo \App\Core\TwigService::view()->render('products/confirm_delete.twig', [
            'row'    => $product,                             // نفس اسم المتغير في مثالك
            'market' => $market,
            'base'   => "{$admin}/markets/{$market_id}/products",
        ]);
    }

    public function destroy(int $market_id, int $id)
{
    if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('CSRF');
    }
    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

    $product = \App\Models\Product::find($id);

    if ($product && (int)$product['market_id'] === $market_id) {

        // حذف ناعم
        \App\Models\Product::softDelete($id);

        $this->putFlash('success', 'تم حذف المنتج بنجاح.');
    }

    header("Location: {$admin}/markets/{$market_id}/products");
    exit;
}

}
