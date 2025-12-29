<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");


use App\Core\Router;
use App\Core\TwigService;
use App\Core\Session;
use App\Services\Auth;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UsersController;
use App\Controllers\OrdersController;
use App\Controllers\CashiersController;
use App\Controllers\PermsController;
use App\Controllers\OptionsController;


Session::start();
TwigService::boot(__DIR__ . '/views', [
    'debug' => (($_ENV['APP_DEBUG'] ?? 'false') === 'true'),
    'cache' => false
]);

// Remember-me auto login
Auth::initRememberedLogin();

$bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
$R  = $bp === '' ? '' : $bp;

$router = new Router();

// Auth
$router->get("$R/login", [AuthController::class, 'showLogin']);
$router->post("$R/login", [AuthController::class, 'login']);
$router->post("$R/logout", [AuthController::class, 'logout']);



// Protected
$router->get("$R/", [DashboardController::class, 'index']);
$router->get("$R/dashboard", [DashboardController::class, 'index']);


// Users (admin-only)
$router->get("$R/users",            [UsersController::class, 'index']);
$router->get("$R/users/create",     [UsersController::class, 'create']);
$router->post("$R/users",            [UsersController::class, 'store']);
$router->get("$R/users/{id:\d+}/edit", [UsersController::class, 'edit']);
$router->post("$R/users/{id:\d+}",      [UsersController::class, 'update']);
/* $router->post("$R/users/{id:\d+}/delete", [UsersController::class, 'delete']); */



// قائمة الطلبات + فلاتر + ترقيم
$router->get("$R/orders", [OrdersController::class, 'index']);
// (اختياري) عرض طلب مفرد
$router->get("$R/orders/{id:\d+}", [OrdersController::class, 'show']);
// حذف طلب (POST + AJAX/Fetch يدعم JSON أو Flash)
$router->post("$R/orders/{id:\d+}/delete", [OrdersController::class, 'delete']);




// ===================== Markets =====================
$router->get("$R/markets",               [\App\Controllers\MarketsController::class, 'index']);
$router->get("$R/markets/{id:\d+}",      [\App\Controllers\MarketsController::class, 'show']);
$router->get("$R/markets/create",        [\App\Controllers\MarketsController::class, 'create']); // admin
$router->post("$R/markets",              [\App\Controllers\MarketsController::class, 'store']);  // admin
$router->get("$R/markets/{id:\d+}/edit", [\App\Controllers\MarketsController::class, 'edit']);   // admin/owner(only own)
$router->post("$R/markets/{id:\d+}",     [\App\Controllers\MarketsController::class, 'update']); // admin/owner(only own)
/* $router->post("$R/markets/{id:\d+}/delete", [\App\Controllers\MarketsController::class, 'delete']); */ // admin
// Cropper
$router->post("$R/markets/{id:\d+}/media", [\App\Controllers\MarketsController::class, 'updateMedia']); // AJAX: تغيير cover/logo





// ===== Cashiers (alias لمستخدمي المتجر) =====
$router->get("$R/markets/{market_id:\d+}/cashiers",               [CashiersController::class, 'index']);
$router->get("$R/markets/{market_id:\d+}/cashiers/create",        [CashiersController::class, 'create']);
$router->post("$R/markets/{market_id:\d+}/cashiers",               [CashiersController::class, 'store']);
$router->get("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}/edit", [CashiersController::class, 'edit']);
$router->post("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}",      [CashiersController::class, 'update']);
/* $router->get("$R/markets/{id:\d+}/cashiers/{mu:\d+}/delete/confirm", [CashiersController::class, 'confirmDelete']);
$router->post("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}/delete", [CashiersController::class, 'destroy']); */
$router->post("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}/status", [CashiersController::class, 'setStatus']);

// قائمة كل الكاشيرات (إدارة عامة)
$router->get("$R/cashiers", [CashiersController::class, 'adminIndex']);


// منتجات كل متجر
$router->get("$R/markets/{market_id:\\d+}/products",                 [\App\Controllers\ProductsController::class, 'index']);
$router->get("$R/markets/{market_id:\\d+}/products/create",          [\App\Controllers\ProductsController::class, 'create']);
$router->post("$R/markets/{market_id:\\d+}/products",                 [\App\Controllers\ProductsController::class, 'store']);
$router->get("$R/markets/{market_id:\\d+}/products/{id:\\d+}",       [\App\Controllers\ProductsController::class, 'show']);
$router->get("$R/markets/{market_id:\\d+}/products/{id:\\d+}/edit",  [\App\Controllers\ProductsController::class, 'edit']);
$router->post("$R/markets/{market_id:\\d+}/products/{id:\\d+}",       [\App\Controllers\ProductsController::class, 'update']);
/* $router->post("$R/markets/{market_id:\\d+}/products/{id:\\d+}/delete", [\App\Controllers\ProductsController::class, 'delete']); */
$router->get("$R/markets/{market_id:\d+}/products/{id:\d+}/delete/confirm", [\App\Controllers\ProductsController::class, 'confirmDelete']);
$router->post("$R/markets/{market_id:\d+}/products/{id:\d+}/delete",         [\App\Controllers\ProductsController::class, 'destroy']);
$router->post('/admincp/markets/{market_id:\d+}/products/{id:\d+}/media', [\App\Controllers\ProductsController::class, 'updateMedia']);

// Cats (أقسام المنتجات)
$router->get("$R/markets/{market_id:\d+}/cats",                 [\App\Controllers\CatsController::class, 'index']);
$router->post("$R/markets/{market_id:\d+}/cats",                 [\App\Controllers\CatsController::class, 'store']);
$router->post("$R/markets/{market_id:\d+}/cats/{id:\d+}/delete", [\App\Controllers\CatsController::class, 'delete']);
$router->get("$R/markets/{market_id:\d+}/cats/{id:\d+}/edit",   [\App\Controllers\CatsController::class, 'edit']);
$router->post("$R/markets/{market_id:\d+}/cats/{id:\d+}",        [\App\Controllers\CatsController::class, 'update']);
$router->get("$R/markets/{market_id:\d+}/cats/{id:\d+}/delete/confirm",     [\App\Controllers\CatsController::class, 'confirmDelete']);
$router->post("$R/markets/{market_id:\d+}/cats/{id:\d+}/delete",             [\App\Controllers\CatsController::class, 'destroy']);
// حفظ الترتيب الجديد
$router->post("$R/markets/{market_id:\d+}/cats/reorder", [\App\Controllers\CatsController::class, 'reorder']);


// ===================== Branch (singular) =====================
// create/store
$router->get("$R/markets/{market_id:\d+}/branch/create",          [\App\Controllers\BranchesController::class, 'create']);
$router->post("$R/markets/{market_id:\d+}/branch",                 [\App\Controllers\BranchesController::class, 'store']);
// show/edit/update/delete
$router->get("$R/markets/{market_id:\d+}/branch/{id:\d+}",        [\App\Controllers\BranchesController::class, 'show']);
$router->get("$R/markets/{market_id:\d+}/branch/{id:\d+}/edit",   [\App\Controllers\BranchesController::class, 'edit']);
$router->post("$R/markets/{market_id:\d+}/branch/{id:\d+}",        [\App\Controllers\BranchesController::class, 'update']);
/* $router->post("$R/markets/{market_id:\d+}/branch/{id:\d+}/delete", [\App\Controllers\BranchesController::class, 'delete']); */


// ===================== Branch Products =====================
$router->get("$R/markets/{market_id:\d+}/branch/{branch_id:\d+}/products",[\App\Controllers\BranchProductsController::class, 'index']);
$router->get("$R/markets/{market_id:\d+}/branch/{branch_id:\d+}/products/{product_id:\d+}/edit",[\App\Controllers\BranchProductsController::class, 'edit']);
$router->post("$R/markets/{market_id:\d+}/branch/{branch_id:\d+}/products/{product_id:\d+}/save",[\App\Controllers\BranchProductsController::class, 'save']);
$router->post("$R/markets/{market_id:\d+}/branch/{branch_id:\d+}/products/{product_id:\d+}/delete",[\App\Controllers\BranchProductsController::class, 'delete']);



// ===================== Options Market =====================

$router->get('/admincp/markets/{market_id}/options',               [OptionsController::class, 'index']);
$router->get('/admincp/markets/{market_id}/options/create',        [OptionsController::class, 'create']);
$router->post('/admincp/markets/{market_id}/options/save',         [OptionsController::class, 'store']);
$router->get('/admincp/markets/{market_id}/options/{id}/edit',     [OptionsController::class, 'edit']);
$router->post('/admincp/markets/{market_id}/options/{id}/save',    [OptionsController::class, 'update']);
$router->get('/admincp/markets/{market_id}/options/{id}/delete/confirm', [OptionsController::class, 'deleteConfirm']);
$router->post('/admincp/markets/{market_id}/options/{id}/delete',        [OptionsController::class, 'destroy']);


// ===================== Option Groups =====================
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups',                [\App\Controllers\OptionGroupsController::class, 'index']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/create',         [\App\Controllers\OptionGroupsController::class, 'create']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups',               [\App\Controllers\OptionGroupsController::class, 'store']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/edit',      [\App\Controllers\OptionGroupsController::class, 'edit']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/save',     [\App\Controllers\OptionGroupsController::class, 'update']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/delete/confirm', [\App\Controllers\OptionGroupsController::class, 'deleteConfirm']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/delete',   [\App\Controllers\OptionGroupsController::class, 'destroy']);

// ✅ صفحة تخصيص الخيارات
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/customize', [\App\Controllers\OptionGroupsController::class, 'customize']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/customize', [\App\Controllers\OptionGroupsController::class, 'saveCustomization']);

// ✅ Ajax (كلها موحدة بالـ {id})
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/ajax-add',     [\App\Controllers\OptionGroupsController::class, 'ajaxAddOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/ajax-remove',  [\App\Controllers\OptionGroupsController::class, 'ajaxRemoveOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/ajax-order',   [\App\Controllers\OptionGroupsController::class, 'ajaxSaveOrder']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/ajax-add-product-option', [\App\Controllers\OptionGroupsController::class, 'ajaxAddProductOption']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/refresh',       [\App\Controllers\OptionGroupsController::class, 'ajaxRefreshOptionsLists']);

// ✅ إدارة خيارات المنتج داخل صفحة التخصيص
$router->post('/admincp/markets/{market_id}/products/{product_id}/product-options/add',            [\App\Controllers\OptionGroupsController::class, 'ajaxAddProductOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/product-options/{id}/update',    [\App\Controllers\OptionGroupsController::class, 'ajaxUpdateProductOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/product-options/{id}/delete',    [\App\Controllers\OptionGroupsController::class, 'ajaxDeleteProductOption']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/product-options/list',            [\App\Controllers\OptionGroupsController::class, 'ajaxListProductOptions']);
// ✅ يظهر كل الخيارات لإدارة الخيارات داخل المودال (المضافة وغير المضافة)
$router->get(
    '/admincp/markets/{market_id}/products/{product_id}/product-options/list_in_managment',
    [\App\Controllers\OptionGroupsController::class, 'ajaxListProductOptionsForManage']
);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{group_id}/refresh', [\App\Controllers\OptionGroupsController::class, 'ajaxRefreshSelectedOptions']);

// ===================== Options Grpups =====================
/* $router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups',          [OptionGroupsController::class, 'index']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/create',   [OptionGroupsController::class, 'create']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups',       [OptionGroupsController::class, 'store']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/edit', [OptionGroupsController::class, 'edit']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/save', [\App\Controllers\OptionGroupsController::class, 'update']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/delete/confirm', [\App\Controllers\OptionGroupsController::class, 'deleteConfirm']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/delete', [\App\Controllers\OptionGroupsController::class, 'destroy']);
// صفحة تخصيص الخيارات
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/customize', [\App\Controllers\OptionGroupsController::class, 'customize']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/customize', [\App\Controllers\OptionGroupsController::class, 'saveCustomization']);
// Ajax add/remove option
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/ajax-add', [\App\Controllers\OptionGroupsController::class, 'ajaxAddOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/ajax-remove', [\App\Controllers\OptionGroupsController::class, 'ajaxRemoveOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/ajax-order', [\App\Controllers\OptionGroupsController::class, 'ajaxSaveOrder']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/option-groups/{group_id}/ajax-add-product-option', [\App\Controllers\OptionGroupsController::class, 'ajaxAddProductOption']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/option-groups/{id}/refresh', [\App\Controllers\OptionGroupsController::class, 'ajaxRefreshOptionsLists']);


// إدارة خيارات المنتج داخل صفحة التخصيص
$router->post('/admincp/markets/{market_id}/products/{product_id}/product-options/add', [\App\Controllers\OptionGroupsController::class, 'ajaxAddProductOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/product-options/{id}/update', [\App\Controllers\OptionGroupsController::class, 'ajaxUpdateProductOption']);
$router->post('/admincp/markets/{market_id}/products/{product_id}/product-options/{id}/delete', [\App\Controllers\OptionGroupsController::class, 'ajaxDeleteProductOption']);
$router->get('/admincp/markets/{market_id}/products/{product_id}/product-options/list', [\App\Controllers\OptionGroupsController::class, 'ajaxListProductOptions']); */

$router->get("$R/markets/{market_id:\d+}/branch/config",[\App\Controllers\BranchConfigController::class, 'index']);
$router->post("$R/markets/{market_id:\d+}/branch/config/save",[\App\Controllers\BranchConfigController::class, 'saveAll']);
$router->post("$R/markets/{market_id:\d+}/branch/{branch_id:\d+}/config/{key}/reset",[\App\Controllers\BranchConfigController::class, 'resetOneConfig']);



// =====================  =====================

$router->get("$R/perms",                                  [PermsController::class, 'list']);
$router->get("$R/perms/create",                           [PermsController::class, 'create']);
$router->post("$R/perms",                                  [PermsController::class, 'store']);
$router->get("$R/perms/{key:[A-Za-z0-9._-]+}/edit",       [PermsController::class, 'edit']);
$router->post("$R/perms/{key:[A-Za-z0-9._-]+}/update",     [PermsController::class, 'update']);
/* $router->get("$R/perms/{key:[A-Za-z0-9._-]+}/delete/confirm", [PermsController::class, 'confirmDelete']);
$router->post("$R/perms/{key:[A-Za-z0-9._-]+}/delete",     [PermsController::class, 'destroy']); */




// =====================  =====================


$router->get("$R/join-requests", [\App\Controllers\JoinRequestsController::class, 'index']);
$router->get("$R/join-requests/{id:\d+}", [\App\Controllers\JoinRequestsController::class, 'show']);
$router->post("$R/join-requests/{id:\d+}/delete", [\App\Controllers\JoinRequestsController::class, 'delete']);
$router->get("$R/join-requests/{id:\d+}/confirm-delete",[\App\Controllers\JoinRequestsController::class, 'confirmDelete']);







// ===================== Grocery Stock (Admin Only) =====================
// ===================== Grocery Stock (Admin Only) =====================
// ===================== Grocery Stock (Admin Only) =====================
// ===================== Grocery Stock (Admin Only) =====================
// ===================== Grocery Stock (Admin Only) =====================
// ===================== Grocery Stock (Admin Only) =====================
// ===================== Grocery Stock =====================
// =============================
// Grocery Stock (المستودع)
// =============================

$router->get("$R/grocery/stock",                     [\App\Controllers\GroceryStockController::class, 'index']);
$router->get("$R/grocery/stock/create",              [\App\Controllers\GroceryStockController::class, 'create']);
$router->post("$R/grocery/stock",                    [\App\Controllers\GroceryStockController::class, 'store']);
$router->get("$R/grocery/stock/{id:\d+}/edit",       [\App\Controllers\GroceryStockController::class, 'edit']);
$router->post("$R/grocery/stock/{id:\d+}/update",    [\App\Controllers\GroceryStockController::class, 'update']);
$router->get("$R/grocery/stock/{id:\d+}/delete",     [\App\Controllers\GroceryStockController::class, 'delete']);

$router->get("$R/grocery/groups/{gid:\d+}/cats",     [\App\Controllers\GroceryStockController::class, 'ajaxCats']);


// =============================
// Grocery Groups (القروبات)
// =============================

$router->get("$R/grocery/groups",                    [\App\Controllers\GroceryGroupsController::class, 'index']);
$router->get("$R/grocery/groups/create",             [\App\Controllers\GroceryGroupsController::class, 'create']);
$router->post("$R/grocery/groups",                   [\App\Controllers\GroceryGroupsController::class, 'store']);
$router->get("$R/grocery/groups/{id:\d+}/edit",      [\App\Controllers\GroceryGroupsController::class, 'edit']);
$router->post("$R/grocery/groups/{id:\d+}/update",   [\App\Controllers\GroceryGroupsController::class, 'update']);
$router->get("$R/grocery/groups/{id:\d+}/delete",    [\App\Controllers\GroceryGroupsController::class, 'delete']);


// =============================
// Grocery Cats (التصنيفات)
// =============================

$router->get("$R/grocery/cats",                      [\App\Controllers\GroceryCatsController::class, 'index']);
$router->get("$R/grocery/cats/create",               [\App\Controllers\GroceryCatsController::class, 'create']);
$router->post("$R/grocery/cats",                     [\App\Controllers\GroceryCatsController::class, 'store']);
$router->get("$R/grocery/cats/{id:\d+}/edit",        [\App\Controllers\GroceryCatsController::class, 'edit']);
$router->post("$R/grocery/cats/{id:\d+}/update",     [\App\Controllers\GroceryCatsController::class, 'update']);
$router->get("$R/grocery/cats/{id:\d+}/delete",      [\App\Controllers\GroceryCatsController::class, 'delete']);


// =============================
// Grocery Brands (العلامات التجارية)
// =============================

$router->get("$R/grocery/brands",                    [\App\Controllers\GroceryBrandsController::class, 'index']);
$router->get("$R/grocery/brands/create",             [\App\Controllers\GroceryBrandsController::class, 'create']);
$router->post("$R/grocery/brands",                   [\App\Controllers\GroceryBrandsController::class, 'store']);
$router->get("$R/grocery/brands/{id:\d+}/edit",      [\App\Controllers\GroceryBrandsController::class, 'edit']);
$router->post("$R/grocery/brands/{id:\d+}/update",   [\App\Controllers\GroceryBrandsController::class, 'update']);
$router->get("$R/grocery/brands/{id:\d+}/delete",    [\App\Controllers\GroceryBrandsController::class, 'delete']);


// =============================
// Grocery Units (الوحدات)
// =============================

$router->get("$R/grocery/units",                     [\App\Controllers\GroceryUnitsController::class, 'index']);
$router->get("$R/grocery/units/create",              [\App\Controllers\GroceryUnitsController::class, 'create']);
$router->post("$R/grocery/units",                    [\App\Controllers\GroceryUnitsController::class, 'store']);
$router->get("$R/grocery/units/{id:\d+}/edit",       [\App\Controllers\GroceryUnitsController::class, 'edit']);
$router->post("$R/grocery/units/{id:\d+}/update",    [\App\Controllers\GroceryUnitsController::class, 'update']);
$router->get("$R/grocery/units/{id:\d+}/delete",     [\App\Controllers\GroceryUnitsController::class, 'delete']);


$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
