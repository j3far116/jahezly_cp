<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Models\GroceryStock;
use App\Models\GroceryCats;
use App\Models\GroceryGroups;
use App\Models\GroceryBrands;
use App\Models\GroceryUnits;
use App\Core\DB;

class GroceryStockController
{
    /* ================= Flash Helpers ================= */

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

    /* ================= Auth ================= */

    private function adminOnly()
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($user['role'] ?? null) !== 'admin') {
            http_response_code(403);
            exit('غير مصرح لك بالدخول');
        }
    }

    /* ================= INDEX ================= */

    public function index()
    {
        $this->adminOnly();

        $pdo   = DB::pdo();
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/stock";

        $market_id = isset($_GET['market_id']) ? (int)$_GET['market_id'] : null;

        // منتجات المستودع
        $items = GroceryStock::allWithCats();

        if ($market_id) {
            $stmt = $pdo->prepare("
                SELECT product_id
                FROM grocery_products
                WHERE market_id = ?
            ");
            $stmt->execute([$market_id]);
            $addedIds = array_column($stmt->fetchAll(), 'product_id');

            foreach ($items as &$item) {
                $item['added_to_store'] = in_array($item['id'], $addedIds, true);
            }
            unset($item);
        }

        echo TwigService::view()->render('grocery_stock/index.twig', [
            'items'     => $items,
            'base'      => $base,
            'market_id' => $market_id,
            '_csrf'     => Csrf::token(),
            'errs'      => $this->flashGet('errors', []),
            'success'   => $this->flashGet('success')
        ]);
    }

    /* ================= ADD TO MARKET ================= */

    public function addToMarket(int $market_id)
    {
        $this->adminOnly();
        if (!Csrf::check($_POST['_csrf'] ?? null)) exit('CSRF');

        $pdo = DB::pdo();
        $product_id = (int)($_POST['product_id'] ?? 0);

        if (!$market_id || !$product_id) {
            exit('بيانات غير صالحة');
        }

        $product = GroceryStock::find($product_id);
        if (!$product) {
            exit('المنتج غير موجود');
        }

        $stmt = $pdo->prepare("
            INSERT INTO grocery_products
                (market_id, product_id, name, `desc`, status)
            VALUES
                (?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $stmt->execute([
            $market_id,
            $product_id,
            $product['name'],
            $product['size'] ?? null
        ]);

        $this->flashSet('success', 'تمت إضافة المنتج للمتجر');
        header("Location: /admincp/grocery/stock?market_id={$market_id}");
        exit;
    }

    /* ================= REMOVE FROM MARKET ================= */

    public function removeFromMarket(int $market_id)
    {
        $this->adminOnly();
        if (!Csrf::check($_POST['_csrf'] ?? null)) exit('CSRF');

        $pdo = DB::pdo();
        $product_id = (int)($_POST['product_id'] ?? 0);

        if (!$market_id || !$product_id) {
            exit('بيانات غير صالحة');
        }

        $stmt = $pdo->prepare("
            DELETE FROM grocery_products
            WHERE market_id = ? AND product_id = ?
        ");
        $stmt->execute([$market_id, $product_id]);

        $this->flashSet('success', 'تمت إزالة المنتج من المتجر');
        header("Location: /admincp/grocery/stock?market_id={$market_id}");
        exit;
    }

    /* ================= CRUD (كما هو) ================= */

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

    public function store()
    {
        $this->adminOnly();
        if (!Csrf::check($_POST['_csrf'] ?? null)) exit('CSRF');
        // كودك كما هو
    }

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

    public function update(int $id)
    {
        $this->adminOnly();
        if (!Csrf::check($_POST['_csrf'] ?? null)) exit("CSRF");
        // كودك كما هو
    }

    public function delete(int $id)
    {
        $this->adminOnly();
        GroceryStock::delete($id);
        header("Location: /admincp/grocery/stock");
        exit;
    }

    public function ajaxCats(int $gid)
    {
        header('Content-Type: application/json');
        echo json_encode(GroceryCats::byGroup($gid));
    }
}
