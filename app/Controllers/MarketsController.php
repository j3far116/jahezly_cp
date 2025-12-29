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

final class MarketsController
{
    public function index(): void
    {
        Gate::allow(['admin','owner']);

        $scopedMarketId = Scope::marketIdForCurrentUser();
        $markets = Market::listForScope($scopedMarketId);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets';

        TwigService::refreshGlobals();
        echo TwigService::view()->render('markets/index.twig', [
            'markets'          => $markets,
            'base'             => $base,
            'scoped_market_id' => $scopedMarketId,
        ]);
    }

public function show(int $id): void
{
    Gate::allow(['admin','owner']);

    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/markets';

    $scopedMarketId = Scope::marketIdForCurrentUser();
    if ($scopedMarketId !== null && $scopedMarketId !== (int)$id) {
        Session::flash('error', 'غير مصرح لك بعرض هذا المتجر.');
        header("Location: {$base}");
        return;
    }

    $market = Market::findById((int)$id);
    if (!$market) {
        Session::flash('error', 'المتجر غير موجود.');
        header("Location: {$base}");
        return;
    }

    $branches = Branch::listByMarketWithLocation((int)$id);
    $branchesBase = $base . '/' . $id . '/branch';

    // 📌 عرض صفحة المتجر العادية دائمًا
    TwigService::refreshGlobals();
    echo TwigService::view()->render('markets/show.twig', [
        'market'           => $market,
        'branches'         => $branches,
        'base'             => $base,
        'branches_base'    => $branchesBase,
        'scoped_market_id' => $scopedMarketId,
        '_csrf'            => Csrf::token(),
    ]);
}



public function create(): void
{
    Gate::allow(['admin']);

    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/markets';

    TwigService::refreshGlobals();
    echo TwigService::view()->render('markets/create.twig', [
        'base'   => $base,
        'values' => [
            'name'   => '',
            'desc'   => '',
            'cover'  => '',
            'logo'   => '',
            'status' => 'inactive',
            'type'   => 1, // النوع الافتراضي
        ],
        'errors' => [],
        '_csrf'  => Csrf::token(),
    ]);
}


public function store(): void
{
    Gate::allow(['admin']);

    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/markets';

    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        Session::flash('error','طلب غير صالح.');
        header("Location: {$base}/create");
        return;
    }

    $values = [
        'name'   => trim($_POST['name'] ?? ''),
        'desc'   => trim($_POST['desc'] ?? ''),
        'status' => $_POST['status'] ?? 'inactive',
        'type'   => (int)($_POST['type'] ?? 1),
        'cover'  => null,
        'logo'   => null,
    ];

    $errors = $this->validate($values);

    if ($errors) {
        TwigService::refreshGlobals();
        echo TwigService::view()->render('markets/create.twig', [
            'base'   => $base,
            'values' => $values,
            'errors' => $errors,
            '_csrf'  => Csrf::token(),
        ]);
        return;
    }

    $id = Market::create($values);

    Session::flash('success','تم إنشاء المتجر بنجاح.');
    header("Location: {$base}/{$id}");
}


public function edit(int $id): void
{
    Gate::allow(['admin','owner']);

    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/markets';

    $markt = Market::findById($id);
    if (!$markt) {
        Session::flash('error','المتجر غير موجود.');
        header("Location: {$base}");
        return;
    }

    // صلاحيات النطاق
    $scopedMarketId = Scope::marketIdForCurrentUser();
    if ($scopedMarketId !== null && $scopedMarketId !== $id) {
        Session::flash('error','غير مصرح لك بتعديل هذا المتجر.');
        header("Location: {$base}");
        return;
    }

    TwigService::refreshGlobals();
    echo TwigService::view()->render('markets/edit.twig', [
        'base'   => $base,
        'id'     => $id,
        'values' => [
            'name'   => $markt['name'],
            'desc'   => $markt['desc'],
            'cover'  => $markt['cover'],
            'logo'   => $markt['logo'],
            'status' => $markt['status'],
            'type'   => $markt['type'], // ← مهم جدًا
        ],
        'errors' => [],
        '_csrf'  => Csrf::token(),
    ]);
}


public function update(int $id): void
{
    Gate::allow(['admin','owner']);

    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/markets';

    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        Session::flash('error','طلب غير صالح.');
        header("Location: {$base}/{$id}/edit");
        return;
    }

    $markt = Market::findById($id);
    if (!$markt) {
        Session::flash('error','المتجر غير موجود.');
        header("Location: {$base}");
        return;
    }

    // صلاحيات النطاق
    $scopedMarketId = Scope::marketIdForCurrentUser();
    if ($scopedMarketId !== null && $scopedMarketId !== $id) {
        Session::flash('error','غير مصرح لك بتعديل هذا المتجر.');
        header("Location: {$base}");
        return;
    }

    $values = [
        'name'   => trim($_POST['name'] ?? ''),
        'desc'   => trim($_POST['desc'] ?? ''),
        'status' => $_POST['status'] ?? 'inactive',
        'type'   => (int)($_POST['type'] ?? $markt['type']), // ← إضافة مهمة جدًا
        'cover'  => $markt['cover'],
        'logo'   => $markt['logo'],
    ];

    $errors = $this->validate($values);

    if ($errors) {
        TwigService::refreshGlobals();
        echo TwigService::view()->render('markets/edit.twig', [
            'base'   => $base,
            'id'     => $id,
            'values' => $values,
            'errors' => $errors,
            '_csrf'  => Csrf::token(),
        ]);
        return;
    }

    Market::updateById($id, $values);

    Session::flash('success','تم تحديث بيانات المتجر.');
    header("Location: {$base}/{$id}");
}


    public function delete(int $id): void
    {
        Gate::allow(['admin']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/markets';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error','طلب غير صالح.');
            header("Location: {$base}");
            return;
        }

        $markt = Market::findById((int)$id);
        if (!$markt) {
            Session::flash('error','المتجر غير موجود.');
            header("Location: {$base}");
            return;
        }

        Market::deleteById((int)$id);
        Session::flash('success','تم حذف المتجر بنجاح.');
        header("Location: {$base}");
    }

    private function validate(array $v): array
    {
        $errors = [];
        if ($v['name'] === '' || mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 100) {
            $errors['name'] = 'الاسم مطلوب (2–100 حرف).';
        }
        if ($v['status'] !== 'active' && $v['status'] !== 'inactive') {
            $errors['status'] = 'الحالة غير صحيحة.';
        }
if (isset($v['cover']) && mb_strlen($v['cover']) > 100) {
    $errors['cover'] = 'اسم الغلاف طويل.';
}

if (isset($v['logo']) && mb_strlen($v['logo']) > 100) {
    $errors['logo'] = 'اسم الشعار طويل.';
}

        return $errors;
    }


    /** AJAX: تحديث صورة الغلاف أو الشعار عبر رفع/قص */
    public function updateMedia(int $id): void
{
    Gate::allow(['admin','owner']);
    header('Content-Type: application/json; charset=UTF-8');

    // CSRF
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>'طلب غير صالح (CSRF).'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // تقييد المالك بمتجره
    $scopedMarketId = \App\Services\Scope::marketIdForCurrentUser();
    if ($scopedMarketId !== null && $scopedMarketId !== (int)$id) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'message'=>'غير مصرح لك.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $market = \App\Models\Market::findById((int)$id);
    if (!$market) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'message'=>'المتجر غير موجود.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $type = $_POST['type'] ?? '';
    if (!in_array($type, ['cover','logo'], true)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>'نوع الصورة غير صحيح.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // ⬇️ مسار الحفظ: /uploads (مجلد واحد)
    $uploadDir = '/uploads';
    $absRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3), '/');
    $absDir    = $absRoot . $uploadDir;

    if (!is_dir($absDir)) @mkdir($absDir, 0775, true);
    if (!is_writable($absDir)) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'message'=>'مجلد الرفع غير قابل للكتابة.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // ✅ مولّد اسم فريد عمليًا (بدون فحص): 20 خانة hex ≈ 80 بت
    $uniqueName = static function (string $ext): string {
        return bin2hex(random_bytes(10)) . '.' . $ext; // مثال: a3f9c1...e2.png
    };

    $savedName = null;

    if (!empty($_FILES['file']['tmp_name'])) {
        // رفع كملف (Blob من الكروبر)
        $tmp  = $_FILES['file']['tmp_name'];
        $mime = mime_content_type($tmp) ?: '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'message'=>'نوع الملف غير مدعوم. يسمح JPEG/PNG/WebP.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
        $savedName = $uniqueName($ext);

        if (!move_uploaded_file($tmp, $absDir . '/' . $savedName)) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'message'=>'فشل حفظ الملف.'], JSON_UNESCAPED_UNICODE);
            return;
        }
    } else {
        // بديل: DataURL (Base64)
        $dataUrl = $_POST['image'] ?? '';
        if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $dataUrl)) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'message'=>'البيانات المرسلة غير صحيحة.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $isPng  = stripos($dataUrl, 'image/png')  !== false;
        $isWebp = stripos($dataUrl, 'image/webp') !== false;
        $ext    = $isPng ? 'png' : ($isWebp ? 'webp' : 'jpg');

        $b64 = preg_replace('#^data:image/(png|jpeg|jpg|webp);base64,#i', '', $dataUrl);
        $bin = base64_decode($b64, true);
        if ($bin === false) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'message'=>'تعذّر قراءة الصورة.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $savedName = $uniqueName($ext);
        if (file_put_contents($absDir . '/' . $savedName, $bin) === false) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'message'=>'فشل حفظ الصورة.'], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    // تحديث الحقل في DB (نخزن الاسم فقط)
    if ($type === 'cover') {
        \App\Models\Market::updateCover((int)$id, $savedName);
    } else {
        \App\Models\Market::updateLogo((int)$id, $savedName);
    }

    echo json_encode([
        'ok'       => true,
        'message'  => 'تم التحديث بنجاح.',
        'filename' => $savedName,
        'url'      => $uploadDir . '/' . $savedName, // /uploads/<hex>.ext
        'type'     => $type,
    ], JSON_UNESCAPED_UNICODE);
}

}
