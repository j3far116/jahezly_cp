<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self';");


use App\Core\Router;
use App\Core\TwigService;
use App\Core\Session;
use App\Services\Auth;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UsersController;
use App\Controllers\OrdersController;
use App\Controllers\CashiersController;


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
$router->get ("$R/users",            [UsersController::class, 'index']);
$router->get ("$R/users/create",     [UsersController::class, 'create']);
$router->post("$R/users",            [UsersController::class, 'store']);
$router->get ("$R/users/{id:\d+}/edit", [UsersController::class, 'edit']);
$router->post("$R/users/{id:\d+}",      [UsersController::class, 'update']);
$router->post("$R/users/{id:\d+}/delete",[UsersController::class, 'delete']);



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
$router->post("$R/markets/{id:\d+}/delete", [\App\Controllers\MarketsController::class, 'delete']); // admin
// Cropper
$router->post("$R/markets/{id:\d+}/media", [\App\Controllers\MarketsController::class, 'updateMedia']); // AJAX: تغيير cover/logo





// ===== Cashiers (alias لمستخدمي المتجر) =====
$router->get ("$R/markets/{market_id:\d+}/cashiers",               [CashiersController::class, 'index']);
$router->get ("$R/markets/{market_id:\d+}/cashiers/create",        [CashiersController::class, 'create']);
$router->post("$R/markets/{market_id:\d+}/cashiers",               [CashiersController::class, 'store']);
$router->get ("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}/edit", [CashiersController::class, 'edit']);
$router->post("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}",      [CashiersController::class, 'update']);
$router->get("$R/markets/{id:\d+}/cashiers/{mu:\d+}/delete/confirm", [CashiersController::class, 'confirmDelete']);
$router->post("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}/delete",[CashiersController::class, 'destroy']);
$router->post("$R/markets/{market_id:\d+}/cashiers/{mu:\d+}/status", [CashiersController::class, 'setStatus']);

// قائمة كل الكاشيرات (إدارة عامة)
$router->get("$R/cashiers", [CashiersController::class, 'adminIndex']);


// ===================== Branch (singular) =====================
// create/store
$router->get ("$R/markets/{market_id:\d+}/branch/create",          [\App\Controllers\BranchesController::class, 'create']);
$router->post("$R/markets/{market_id:\d+}/branch",                 [\App\Controllers\BranchesController::class, 'store']);
// show/edit/update/delete
$router->get ("$R/markets/{market_id:\d+}/branch/{id:\d+}",        [\App\Controllers\BranchesController::class, 'show']);
$router->get ("$R/markets/{market_id:\d+}/branch/{id:\d+}/edit",   [\App\Controllers\BranchesController::class, 'edit']);
$router->post("$R/markets/{market_id:\d+}/branch/{id:\d+}",        [\App\Controllers\BranchesController::class, 'update']);
$router->post("$R/markets/{market_id:\d+}/branch/{id:\d+}/delete", [\App\Controllers\BranchesController::class, 'delete']);



$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);