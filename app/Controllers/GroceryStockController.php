<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Models\GroceryStock;
use App\Models\GroceryCats;
use App\Models\GroceryGroups;
use App\Models\GroceryBrands;
use App\Models\GroceryUnits;


class GroceryStockController
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


    // ---------------------------------------------------------
    public function index()
    {
        $this->adminOnly();

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/stock";

        $items = GroceryStock::allWithCats();

        echo TwigService::view()->render('grocery_stock/index.twig', [
            'items' => $items,
            'base'  => $base,
            'errs'  => $this->flashGet('errors', []),
            'success' => $this->flashGet('success')
        ]);
    }

    // ---------------------------------------------------------
public function create()
{
    $this->adminOnly();

    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

    echo TwigService::view()->render('grocery_stock/create.twig', [
        'groups' => GroceryGroups::all(),
        'brands' => GroceryBrands::all(),
        'units'  => GroceryUnits::all(),
        'base'   => "$admin/grocery/stock"
    ]);
}


    // ---------------------------------------------------------
public function store()
{
    $this->adminOnly();

    if (!Csrf::check($_POST['_csrf'] ?? null)) exit('CSRF');

    $name     = trim($_POST['name'] ?? '');
    $barcode  = trim($_POST['barcode'] ?? '');
    $cat_id   = intval($_POST['cat_id'] ?? 0);
    $brand_id = intval($_POST['brand_id'] ?? 0);
    $unit_id  = intval($_POST['unit_id'] ?? 0);
    $size     = trim($_POST['size'] ?? '');
    $status   = $_POST['status'] ?? 'active';

    $errors = [];

    if ($name === '')   $errors['name'] = 'اسم المنتج مطلوب';
    if ($barcode === '') $errors['barcode'] = 'الباركود مطلوب';
    if ($cat_id === 0)  $errors['cat_id'] = 'التصنيف مطلوب';
    if ($unit_id === 0)  $errors['unit_id'] = 'الوحدة مطلوبة';
    if ($size === '')    $errors['size'] = 'الحجم مطلوب';
if ($brand_id === 0) {
    $brand_id = null; // اجعلها null بدل رفضها
}

    // تحقق من القروب عبر التصنيف
$cat = GroceryCats::find($cat_id);
if (!$cat) {
    $errors['cat_id'] = 'تصنيف غير صالح';
} else {
    if (empty($cat['group_id'])) {
        $errors['group'] = 'القروب غير صالح';
    }
}

    if (!empty($errors)) {
        $this->flashSet('errors', $errors);
        $this->flashSet('old', $_POST);
        header("Location: /admincp/grocery_stock/create");
        exit;
    }

    // الصورة اختيارية
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $filename = uniqid() . "_" . basename($_FILES['image']['name']);
        $path = __DIR__ . "/../../uploads/grocery/" . $filename;
        move_uploaded_file($_FILES['image']['tmp_name'], $path);
        $image = $filename;
    }

    GroceryStock::create([
        'name'     => $name,
        'barcode'  => $barcode,
        'brand_id' => $brand_id ?: null,
        'unit_id'  => $unit_id,
        'cat_id'   => $cat_id,
        'size'     => $size,
        'image'    => $image,
        'status'   => $status
    ]);

    header("Location: /admincp/grocery/stock");
    exit;
}



    // ---------------------------------------------------------
public function edit(int $id)
{
    $this->adminOnly();

    $item = GroceryStock::find($id);
    if (!$item) exit("Item not found");

    echo TwigService::view()->render('grocery_stock/edit.twig', [
        'row'    => $item,
        'groups' => GroceryGroups::all(),
        'cats'   => GroceryCats::all(),
        'brands' => GroceryBrands::all(),
        'units'  => GroceryUnits::all(),
        'base'   => "/admincp/grocery/stock",
    ]);
}


    // ---------------------------------------------------------
public function update(int $id)
{
    $this->adminOnly();

    if (!Csrf::check($_POST['_csrf'] ?? null)) exit("CSRF");

    $name     = trim($_POST['name'] ?? '');
    $barcode  = trim($_POST['barcode'] ?? '');
    $cat_id   = intval($_POST['cat_id'] ?? 0);
    $brand_id = intval($_POST['brand_id'] ?? 0);
    $unit_id  = intval($_POST['unit_id'] ?? 0);
    $size     = trim($_POST['size'] ?? '');
    $status   = $_POST['status'] ?? 'active';

    $errors = [];

    if ($name === '')   $errors['name'] = 'اسم المنتج مطلوب';
    if ($barcode === '') $errors['barcode'] = 'الباركود مطلوب';
    if ($cat_id === 0)  $errors['cat_id'] = 'التصنيف مطلوب';
    if ($unit_id === 0)  $errors['unit_id'] = 'الوحدة مطلوبة';
    if ($size === '')    $errors['size'] = 'الحجم مطلوب';
if ($brand_id === 0) {
    $brand_id = null; // اجعلها null بدل رفضها
}

    if (!empty($errors)) {
        $this->flashSet('errors', $errors);
        $this->flashSet('old', $_POST);
        header("Location: /admincp/grocery_stock/{$id}/edit");
        exit;
    }

    $data = [
        'name'     => $name,
        'barcode'  => $barcode,
        'brand_id' => $brand_id ?: null,
        'unit_id'  => $unit_id,
        'cat_id'   => $cat_id,
        'size'     => $size,
        'status'   => $status,
    ];

    if (!empty($_FILES['image']['name'])) {
        $filename = uniqid() . "_" . basename($_FILES['image']['name']);
        $path = __DIR__ . "/../../uploads/grocery/" . $filename;
        move_uploaded_file($_FILES['image']['tmp_name'], $path);
        $data['image'] = $filename;
    }

    GroceryStock::update($id, $data);

    header("Location: /admincp/grocery/stock");
    exit;
}



    // ---------------------------------------------------------
    public function delete(int $id)
    {
        $this->adminOnly();

        GroceryStock::delete($id);

        header("Location: /admincp/grocery/stock");
        exit;
    }

    // ---------------------------------------------------------
    public function ajaxCats(int $gid)
    {
        header('Content-Type: application/json');
        echo json_encode(GroceryCats::byGroup($gid));
    }
}
