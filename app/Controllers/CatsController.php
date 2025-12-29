<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\TwigService;
use App\Models\Market;
use App\Models\Cat;
use App\Services\Scope;

class CatsController
{
    private function pullFlash(string $key, $default = null)
    {
        $k = '_flash_' . $key;
        $v = $_SESSION[$k] ?? $default;
        if (array_key_exists($k, $_SESSION)) unset($_SESSION[$k]);
        return $v;
    }
    private function putFlash(string $key, $value): void
    {
        $_SESSION['_flash_' . $key] = $value;
    }
    public function index(int $market_id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $owner = \App\Services\Scope::marketIdForCurrentUser();

        // 🔹 التحقق من النطاق
        if ($owner !== null && $owner !== $market_id) {
            $this->putFlash('errors', ['auth' => 'غير مصرح']);
            header("Location: {$admin}/markets");
            exit;
        }

        // 🔹 التحقق من المتجر
        $market = \App\Models\Market::findById($market_id);
        if (!$market) {
            http_response_code(404);
            exit('Market not found');
        }

        // 🔹 نوع الأقسام (افتراضي products)
        $type = isset($_GET['type']) && is_string($_GET['type']) ? trim($_GET['type']) : 'products';

        // 🔹 جلب الأقسام
        $cats = \App\Models\Cat::allByMarketType($market_id, $type);
        if (!is_array($cats)) $cats = [];

        // 🔹 ترتيب الأقسام محليًا احتياطيًا
        usort($cats, static function (array $a, array $b) {
            $sa = isset($a['sort']) ? (int)$a['sort'] : 0;
            $sb = isset($b['sort']) ? (int)$b['sort'] : 0;
            return $sa === $sb
                ? ((int)$a['id']) <=> ((int)$b['id'])
                : $sa <=> $sb;
        });

        // 🔥 🔥 إضافة عدد المنتجات لكل قسم
        foreach ($cats as &$c) {
            $c['products_count'] = \App\Models\Product::countByCat($market_id, (int)$c['id']);
        }
        unset($c);

        // 🔹 روابط للقالب
        $base        = "{$admin}/markets/{$market_id}/cats";
        $reorderUrl  = $base . '/reorder' . ($type ? ('?type=' . urlencode($type)) : '');
        $backProducts = "{$admin}/markets/{$market_id}/products";

        // 🔹 عرض القالب
        echo \App\Core\TwigService::view()->render('cats/index.twig', [
            'market'           => $market,
            'cats'             => $cats,
            'type'             => $type,
            'base'             => $base,
            'reorder_url'      => $reorderUrl,
            'back_products'    => $backProducts,
            'errs'             => $this->pullFlash('errors', []),
            'old'              => $this->pullFlash('old', []),
            'scoped_market_id' => $owner,
        ]);
    }




    public function store(int $market_id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) {
            $this->putFlash('errors', ['name' => 'الاسم مطلوب وبحد أقصى 255']);
            $this->putFlash('old', $_POST);
            header('Location: ' . "{$admin}/markets/{$market_id}/cats");
            exit;
        }
        Cat::create(['market_id' => $market_id, 'name' => $name, 'type' => 'products']);
        header('Location: ' . "{$admin}/markets/{$market_id}/cats");
        exit;
    }

    public function edit(int $market_id, int $id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $owner = Scope::marketIdForCurrentUser();
        if ($owner !== null && $owner !== $market_id) {
            http_response_code(403);
            exit('Forbidden');
        }

        $market = Market::findById($market_id);
        if (!$market) {
            http_response_code(404);
            exit('Market not found');
        }

        $cat = Cat::find($id);
        if (!$cat || (int)$cat['market_id'] !== $market_id || $cat['type'] !== 'products') {
            http_response_code(404);
            exit('Category not found');
        }

        echo TwigService::view()->render('cats/edit.twig', [
            'market'           => $market,
            'cat'              => $cat,
            'base'             => "{$admin}/markets/{$market_id}/cats",
            'back'             => "{$admin}/markets/{$market_id}/cats",
            'errs'             => $this->pullFlash('errors', []),
            'old'              => $this->pullFlash('old', []),
            'scoped_market_id' => $owner,
        ]);
    }

    public function update(int $market_id, int $id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $cat = Cat::find($id);
        if (!$cat || (int)$cat['market_id'] !== $market_id || $cat['type'] !== 'products') {
            http_response_code(404);
            exit('Category not found');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) {
            $this->putFlash('errors', ['name' => 'الاسم مطلوب وبحد أقصى 255']);
            $this->putFlash('old', $_POST);
            header('Location: ' . "{$admin}/markets/{$market_id}/cats/{$id}/edit");
            exit;
        }

        Cat::update($id, ['name' => $name]);
        header('Location: ' . "{$admin}/markets/{$market_id}/cats");
        exit;
    }

    public function delete(int $market_id, int $id)
    {
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }
        $cat = Cat::find($id);
        if ($cat && (int)$cat['market_id'] === $market_id && $cat['type'] === 'products') {
            Cat::delete($id);
        }
        header('Location: ' . "{$admin}/markets/{$market_id}/cats");
        exit;
    }

public function confirmDelete(int $market_id, int $id)
{
    $admin  = $_SERVER['BASE_PATH'] ?? '/admincp';
    $market = Market::findById($market_id);
    $cat    = Cat::find($id);

    if (!$market || !$cat || (int)$cat['market_id'] !== $market_id || $cat['type'] !== 'products') {
        http_response_code(404);
        exit('Category not found');
    }

    $count = \App\Models\Product::countByCat($market_id, $id);

    if ($count > 0) {
        $this->putFlash('errors', [
            "لا يمكن حذف هذا القسم لأنه يحتوي على {$count} منتجًا مضافًا."
        ]);

        header("Location: {$admin}/markets/{$market_id}/cats");
        exit;
    }

    // → حذف ناعم بدلاً من الحذف النهائي
    Cat::softDelete($id);

    $this->putFlash('success', 'تمت إزالة القسم بنجاح.');
    header("Location: {$admin}/markets/{$market_id}/cats");
    exit;
}




public function destroy(int $market_id, int $id)
{
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        http_response_code(400); exit('CSRF');
    }

    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
    $cat = Cat::find($id);

    if ($cat && (int)$cat['market_id'] === $market_id && $cat['type'] === 'products') {

        $count = \App\Models\Product::countByCat($market_id, $id);

        if ($count > 0) {
            $this->putFlash('errors', [
                "لا يمكن حذف هذا القسم لأنه يحتوي على {$count} منتجًا."
            ]);
            header("Location: {$admin}/markets/{$market_id}/cats");
            exit;
        }

        Cat::softDelete($id);

        $this->putFlash('success', 'تمت إزالة القسم بنجاح.');
    }

    header("Location: {$admin}/markets/{$market_id}/cats");
    exit;
}




    public function reorder(int $market_id)
    {
        \App\Core\Csrf::check($_POST['_csrf'] ?? null);

        $type = $_GET['type'] ?? null; // نتوقع 'products'
        $ids  = $_POST['ids'] ?? [];

        header('Content-Type: application/json');

        if (!is_array($ids) || empty($ids)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'msg' => 'لا توجد عناصر لإعادة ترتيبها.']);
            return;
        }

        // أرقام فقط + إزالة التكرارات
        $ids = array_values(array_unique(array_map(static function ($v) {
            return (int)$v;
        }, $ids)));
        if (empty($ids)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'msg' => 'لا توجد عناصر صحيحة لإعادة ترتيبها.']);
            return;
        }

        $pdo = \App\Core\DB::pdo();

        try {
            // تحقق الملكية والنوع
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT id, market_id, type FROM cats WHERE id IN ($in)";
            $stm = $pdo->prepare($sql);
            $stm->execute($ids);
            $rows = $stm->fetchAll(\PDO::FETCH_ASSOC);

            $byId = [];
            foreach ($rows as $r) {
                $byId[(int)$r['id']] = $r;
            }

            foreach ($ids as $id) {
                if (!isset($byId[$id]) || (int)$byId[$id]['market_id'] !== $market_id) {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'msg' => 'عنصر غير صالح أو لا يتبع هذا المتجر.']);
                    return;
                }
                if ($type && $byId[$id]['type'] !== $type) {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'msg' => 'نوع قسم غير متطابق.']);
                    return;
                }
            }

            // تحديث sort بفجوات 10
            $upd  = $pdo->prepare("UPDATE cats SET sort = ? WHERE id = ?");
            $sort = 10;
            foreach ($ids as $id) {
                $upd->execute([$sort, $id]);
                $sort += 10;
            }

            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'فشل الحفظ']);
        }
    }
}
