<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Models\GroceryBrands;

class GroceryBrandsController
{
    private function flashGet(string $key, $default = null)
    {
        $k = '_flash_' . $key;
        $v = $_SESSION[$k] ?? $default;
        if (isset($_SESSION[$k])) unset($_SESSION[$k]);
        return $v;
    }

    private function flashSet(string $key, $value)
    {
        $_SESSION['_flash_' . $key] = $value;
    }

    private function adminOnly()
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($user['role'] ?? null) !== 'admin') {
            http_response_code(403);
            exit('غير مصرح لك بالدخول');
        }
    }

    // -------------------- index --------------------
    public function index()
    {
        $this->adminOnly();

        $admin  = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base   = "$admin/grocery/brands";
        $rows   = GroceryBrands::all();

        echo TwigService::view()->render('grocery_brands/index.twig', [
            'brands'  => $rows,
            'base'    => $base,
            'errs'    => $this->flashGet('errors', []),
            'success' => $this->flashGet('success'),
        ]);
    }

    // -------------------- create --------------------
    public function create()
    {
        $this->adminOnly();

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/brands";

        echo TwigService::view()->render('grocery_brands/create.twig', [
            'base' => $base,
            'errs' => $this->flashGet('errors', []),
            'old'  => $this->flashGet('old', []),
        ]);
    }

    // -------------------- store --------------------
    public function store()
    {
        $this->adminOnly();

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $name   = trim($_POST['name'] ?? '');
        $status = $_POST['status'] ?? 'active';

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'اسم العلامة التجارية مطلوب';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'] = 'الاسم أطول من المسموح (255 حرفًا)';
        }

        if (!in_array($status, ['active','inactive'], true)) {
            $status = 'active';
        }

        // logo اختياري
        $logo = null;
        if (!empty($_FILES['logo']['name'])) {
            $filename = uniqid('brand_') . '_' . basename($_FILES['logo']['name']);
            $uploadDir = __DIR__ . '/../../uploads/grocery_brands';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $path = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $path)) {
                $logo = $filename;
            } else {
                $errors['logo'] = 'فشل رفع صورة الشعار، جرّب مرة أخرى.';
            }
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header('Location: /admincp/grocery/brands/create');
            exit;
        }

        GroceryBrands::create([
            'name'   => $name,
            'logo'   => $logo,
            'status' => $status,
        ]);

        $this->flashSet('success', 'تمت إضافة العلامة التجارية بنجاح.');
        header('Location: /admincp/grocery/brands');
        exit;
    }

    // -------------------- edit --------------------
    public function edit(int $id)
    {
        $this->adminOnly();

        $brand = GroceryBrands::find($id);
        if (!$brand) {
            http_response_code(404);
            exit('Brand not found');
        }

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/brands";

        echo TwigService::view()->render('grocery_brands/edit.twig', [
            'row'  => $brand,
            'base' => $base,
            'errs' => $this->flashGet('errors', []),
            'old'  => $this->flashGet('old', []),
        ]);
    }

    // -------------------- update --------------------
    public function update(int $id)
    {
        $this->adminOnly();

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $brand = GroceryBrands::find($id);
        if (!$brand) {
            http_response_code(404);
            exit('Brand not found');
        }

        $name   = trim($_POST['name'] ?? '');
        $status = $_POST['status'] ?? 'active';

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'اسم العلامة التجارية مطلوب';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'] = 'الاسم أطول من المسموح (255 حرفًا)';
        }

        if (!in_array($status, ['active','inactive'], true)) {
            $status = 'active';
        }

        $logo = $brand['logo'] ?? null;
        if (!empty($_FILES['logo']['name'])) {
            $filename = uniqid('brand_') . '_' . basename($_FILES['logo']['name']);
            $uploadDir = __DIR__ . '/../../uploads/grocery_brands';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $path = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $path)) {
                $logo = $filename;
            } else {
                $errors['logo'] = 'فشل رفع صورة الشعار، جرّب مرة أخرى.';
            }
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header("Location: /admincp/grocery/brands/{$id}/edit");
            exit;
        }

        GroceryBrands::update($id, [
            'name'   => $name,
            'logo'   => $logo,
            'status' => $status,
        ]);

        $this->flashSet('success', 'تم تحديث العلامة التجارية بنجاح.');
        header('Location: /admincp/grocery/brands');
        exit;
    }

    // -------------------- delete --------------------
    public function delete(int $id)
    {
        $this->adminOnly();

        $brand = GroceryBrands::find($id);
        if ($brand) {
            GroceryBrands::delete($id);
            $this->flashSet('success', 'تم حذف العلامة التجارية بنجاح.');
        }

        header('Location: /admincp/grocery/brands');
        exit;
    }
}
