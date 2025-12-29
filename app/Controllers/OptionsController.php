<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\TwigService;
use App\Models\Option;
use App\Models\Market;
use App\Services\Scope;
use App\Core\DB;

final class OptionsController
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

    /**
     * عرض قائمة الإضافات (الخيارات)
     */
    public function index(int $market_id)
    {
        $admin = $_ENV['BASE_PATH'] ?? '/admincp';
        $ownerMid = Scope::marketIdForCurrentUser();

        // التحقق من صلاحية المالك
        if ($ownerMid !== null && $ownerMid !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header("Location: {$admin}/markets/{$ownerMid}/options");
            exit;
        }

        $market = Market::findById($market_id);
        if (!$market) {
            http_response_code(404);
            exit('Market not found');
        }

        $options = Option::allForMarket($market_id);

        echo TwigService::view()->render('options/index.twig', [
            'market'           => $market,
            'options'          => $options,
            'base'             => "{$admin}/markets/{$market_id}/options",
            'admin_base'       => $admin,
            'scoped_market_id' => $ownerMid,
            'success'          => $this->pullFlash('success'),
            'errors'           => $this->pullFlash('errors', []),
        ]);
    }

    /**
     * صفحة إنشاء خيار جديد
     */
    public function create(int $market_id)
    {
        $admin = $_ENV['BASE_PATH'] ?? '/admincp';
        $market = Market::findById($market_id);
        if (!$market) {
            http_response_code(404);
            exit('Market not found');
        }

        echo TwigService::view()->render('options/create.twig', [
            'market'           => $market,
            'base'             => "{$admin}/markets/{$market_id}/options",
            'errs'             => $this->pullFlash('errors', []),
            'old'              => $this->pullFlash('old', []),
            'scoped_market_id' => Scope::marketIdForCurrentUser(),
        ]);
    }

    /**
     * تخزين خيار جديد
     */
    public function store(int $market_id)
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $admin = $_ENV['BASE_PATH'] ?? '/admincp';
        $data = $this->validateOrBack($_POST, $market_id, "{$admin}/markets/{$market_id}/options/create");

        Option::create($data);
        $this->putFlash('success', 'تمت إضافة الخيار بنجاح');
        header("Location: {$admin}/markets/{$market_id}/options");
        exit;
    }

    /**
     * صفحة تعديل خيار
     */
    public function edit(int $market_id, int $id)
    {
        $admin = $_ENV['BASE_PATH'] ?? '/admincp';

        $item = Option::find($id);
        if (!$item || (int)($item['market_id'] ?? 0) !== (int)$market_id) {
    http_response_code(404);
    exit('Not found');
}

        echo TwigService::view()->render('options/edit.twig', [
            'item'             => $item,
            'base'             => "{$admin}/markets/{$market_id}/options",
            'errs'             => $this->pullFlash('errors', []),
            'old'              => $this->pullFlash('old', []),
            'scoped_market_id' => Scope::marketIdForCurrentUser(),
        ]);
    }

    /**
     * تحديث خيار
     */
    public function update(int $market_id, int $id)
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $admin = $_ENV['BASE_PATH'] ?? '/admincp';
        $item = Option::find($id);
        if (!$item || (int)($item['market_id'] ?? 0) !== (int)$market_id) {
            http_response_code(404);
            exit('Option not found');
        }

        $data = $this->validateOrBack($_POST, $market_id, "{$admin}/markets/{$market_id}/options/{$id}/edit");

        Option::updateById($id, $data);
        $this->putFlash('success', 'تم تحديث الخيار بنجاح');
        header("Location: {$admin}/markets/{$market_id}/options");
        exit;
    }

    /**
     * تأكيد حذف
     */
    /* public function deleteConfirm(int $market_id, int $id)
    {
        $admin  = $_SERVER['BASE_PATH'] ?? '/admincp';
        $item   = Option::find($id);
        $market = Market::findById($market_id);

        if (!$item || !$market ||  (int)($item['market_id'] ?? 0) !== (int)$market_id) {

            http_response_code(404);
            exit('Option not found');
        }

        echo TwigService::view()->render('options/confirm_delete.twig', [
            'row'    => $item,
            'market' => $market,
            'base'   => "{$admin}/markets/{$market_id}/options",
        ]);
    } */

        public function deleteConfirm(int $market_id, int $id)
{
    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
$isUsed = \App\Models\Option::isUsedInGroups($id);

$warningMessage = null;
if ($isUsed) {
    $warningMessage = "⚠️ لا يمكن حذف هذا الخيار لأنه مستخدم في مجموعة خيارات. يمكنك تعطيله أو إزالته من المجموعة أولًا.";
}

    $option = \App\Models\Option::find($id);
    if (!$option || (int)$option['market_id'] !== $market_id) {
        http_response_code(404);
        exit('الخيار غير موجود');
    }

    // ✅ تحقق إن كان هذا الخيار مستخدمًا في أي مجموعة خيارات
    $pdo = \App\Core\DB::pdo();
    $stmt = $pdo->prepare("SELECT id, name FROM options_group WHERE JSON_CONTAINS(options, :search, '$')");
    $stmt->execute(['search' => json_encode(['id' => (int)$id])]);
    $usedGroup = $stmt->fetch(\PDO::FETCH_ASSOC);

    $isUsed = false;
    $warningMessage = null;

    if ($usedGroup) {
        $isUsed = true;
        $warningMessage = "⚠️ لا يمكن حذف هذا الخيار لأنه مستخدم في مجموعة خيارات (<b>{$usedGroup['name']}</b>). يمكنك تعطيله أو إزالته من المجموعة أولًا.";
    }

    $market = \App\Models\Market::findById($market_id);

    echo \App\Core\TwigService::view()->render('options/confirm_delete.twig', [
        'market'  => $market,
        'option'  => $option,
        'base'    => "{$admin}/markets/{$market_id}/options",
        'is_used' => $isUsed,
        'warning' => $warningMessage,
    ]);
}


    /**
     * تنفيذ الحذف
     */
public function destroy(int $market_id, int $id)
{
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('CSRF');
    }

    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
    $item  = Option::find($id);

    if (!$item || (int)$item['market_id'] !== (int)$market_id) {
        $this->putFlash('errors', ['notfound' => 'الخيار غير موجود أو لا يتبع هذا المتجر']);
        header("Location: {$admin}/markets/{$market_id}/options");
        exit;
    }

    // 🚫 منع الحذف إذا كان مستخدمًا
    if (\App\Models\Option::isUsedInGroups($id)) {
        $this->putFlash('errors', [
            'used' => 'لا يمكن حذف هذا الخيار لأنه مستخدم في منتجات.'
        ]);
        header("Location: {$admin}/markets/{$market_id}/options");
        exit;
    }

    // ✅ الحذف الآمن
    Option::deleteById($id);
    $this->putFlash('success', '🗑️ تم حذف الخيار بنجاح');

    header("Location: {$admin}/markets/{$market_id}/options");
    exit;
}



    /**
     * التحقق من صحة البيانات وإعادة التوجيه عند الخطأ
     */
    private function validateOrBack(array $in, int $market_id, string $backUrl): array
    {
        $errors = [];

        $name = trim($in['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'الاسم مطلوب وبحد أقصى 100 حرف';
        }

        $price = trim((string)($in['price'] ?? ''));
        if ($price === '' || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $price)) {
            $errors['price'] = 'السعر غير صالح';
        }

        $available = (int)($in['available'] ?? 1);
        if (!in_array($available, [0, 1], true)) {
            $errors['available'] = 'قيمة الحالة غير صالحة';
        }


        if ($errors) {
            $this->putFlash('errors', $errors);
            $this->putFlash('old', $in);
            header('Location: ' . $backUrl);
            exit;
        }

        return [
            'market_id'  => $market_id,
            'name'       => $name,
            'price'      => number_format((float)$price, 2, '.', ''),
            'available'  => $available,
        ];
    }

    
}
