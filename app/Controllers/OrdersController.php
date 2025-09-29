<?php
namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Session;
use App\Core\Csrf;
use App\Services\Gate;
use App\Models\Order;
use App\Services\Scope;

final class OrdersController
{
    // قوائم القيم المسموحة (للواجهة والتحقق)
    private array $allowedStatus = ['request','review','waitPay','received','prepare','ready','complate','rejected','canceled','returned'];
    private array $allowedPayType = ['onReceived','onMada'];
    private array $allowedPayStatus = ['pending','paid','failed','refunded'];

public function index(): void
{
    Gate::allow(['admin','owner']); // أو حسب نظامك
    $scopedMarketId = Scope::marketIdForCurrentUser();

    $page     = (int)($_GET['page']     ?? 1);
    $perPage  = (int)($_GET['per_page'] ?? 20);
    $status   = $_GET['status']     ?? '';
    $payType  = $_GET['pay_type']   ?? '';
    $payStat  = $_GET['pay_status'] ?? '';

    // إن كان المستخدم مُقيّداً بسوق: تجاهل أي market_id قادم من GET
    if ($scopedMarketId !== null) {
        $marketId = $scopedMarketId;
    } else {
        $marketId = isset($_GET['market_id']) && $_GET['market_id'] !== '' ? (int)$_GET['market_id'] : null;
    }

    $userId   = isset($_GET['user_id'])   && $_GET['user_id']   !== '' ? (int)$_GET['user_id']   : null;

    $sortField = $_GET['sort'] ?? 'created_at';
    $sortDir   = $_GET['dir']  ?? 'desc';

    // تطبيع القيم (كما كنت تفعل سابقاً)
    $status   = in_array($status,   $this->allowedStatus,    true) ? $status : null;
    $payType  = in_array($payType,  $this->allowedPayType,   true) ? $payType : null;
    $payStat  = in_array($payStat,  $this->allowedPayStatus, true) ? $payStat : null;

    $filters = [
        'status'     => $status,
        'pay_status' => $payStat,
        'pay_type'   => $payType,
        'market_id'  => $marketId,
        'user_id'    => $userId,
    ];
    $sort = ['field' => $sortField, 'dir' => $sortDir];

    // ✅ مرّر الـ market_id الإجباري للموديل (انظر الفقرة 3)
    $result = \App\Models\Order::paginate($page, $perPage, $filters, $sort, $scopedMarketId);

    // إعداد الروابط مع الحفاظ على الفلاتر (مع عدم تضمين market_id إذا مقفول)
    $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
    $base = $bp . '/orders';
    $persist = [];

    if ($status)   $persist['status']     = $status;
    if ($payType)  $persist['pay_type']   = $payType;
    if ($payStat)  $persist['pay_status'] = $payStat;
    if ($userId !== null) $persist['user_id'] = $userId;
    if ($perPage && $perPage !== 20) $persist['per_page'] = $perPage;
    if ($sortField) $persist['sort'] = $sortField;
    if ($sortDir)   $persist['dir']  = $sortDir;

    // لا نمرر market_id في الروابط إن كان مقفولًا
    if ($scopedMarketId === null && $marketId !== null) {
        $persist['market_id'] = $marketId;
    }

    $qs = function(array $extra = []) use ($persist) {
        $q = array_merge($persist, $extra);
        return $q ? ('?' . http_build_query($q)) : '';
    };

    $pagination = [
        'prev_url'  => ($result['page'] > 1) ? ($base . $qs(['page' => $result['page'] - 1])) : null,
        'next_url'  => ($result['page'] < $result['total_pages']) ? ($base . $qs(['page' => $result['page'] + 1])) : null,
        'first_url' => ($result['page'] > 1) ? ($base . $qs(['page' => 1])) : null,
        'last_url'  => ($result['page'] < $result['total_pages']) ? ($base . $qs(['page' => $result['total_pages']])) : null,
    ];

    \App\Core\TwigService::refreshGlobals();
    echo TwigService::view()->render('orders/index.twig', [
        'orders'         => $result['data'],
        'page'           => $result['page'],
        'per_page'       => $result['per_page'],
        'total'          => $result['total'],
        'total_pages'    => $result['total_pages'],
        'pagination'     => $pagination,
        'allowedStatus'  => $this->allowedStatus,
        'allowedPayType' => $this->allowedPayType,
        'allowedPayStatus' => $this->allowedPayStatus,
        'filters'        => [
            'status'     => $status,
            'pay_type'   => $payType,
            'pay_status' => $payStat,
            'market_id'  => $marketId, // قد تكون null عند التقييد
            'user_id'    => $userId,
            'sort'       => $sortField,
            'dir'        => $sortDir,
        ],
        'base'           => $base,
        // 👇 سنحتاجه في الواجهة لإخفاء حقل market_id عند التقييد
        'scoped_market_id' => $scopedMarketId,
    ]);
}

    public function show(int $id): void
    {
        Gate::allow(['admin']);
        $o = Order::findById($id);
        if (!$o) {
            Session::flash('error', 'الطلب غير موجود.');
            header('Location: ' . rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/') . '/orders');
            return;
        }

        $scopedMarketId = Scope::marketIdForCurrentUser();
    if ($scopedMarketId !== null && (int)$o['market_id'] !== $scopedMarketId) {
        Session::flash('error', 'غير مصرح لك بعرض هذا الطلب.');
        header('Location: ' . rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/') . '/orders');
        return;
    }


        \App\Core\TwigService::refreshGlobals();
        echo TwigService::view()->render('orders/show.twig', ['o' => $o]);
    }

    public function delete(int $id): void
    {
        Gate::allow(['admin']);
        $bp = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->respondDelete(false, 'طلب غير صالح.', $bp);
            return;
        }

        $o = Order::findById($id);
        
        $scopedMarketId = \App\Services\Scope::marketIdForCurrentUser();
if ($scopedMarketId !== null && (int)$o['market_id'] !== $scopedMarketId) {
    $this->respondDelete(false, 'غير مصرح لك بحذف هذا الطلب.', $bp);
    return;
}

        if (!$o) {
            $this->respondDelete(false, 'الطلب غير موجود.', $bp);
            return;
        }



        Order::deleteById($id);
        $this->respondDelete(true, 'تم حذف الطلب بنجاح.', $bp);
    }

    private function respondDelete(bool $ok, string $msg, string $bp): void
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJson = str_contains($accept, 'application/json')
               || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

        if ($isJson) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code($ok ? 200 : 400);
            echo json_encode(['ok' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
            return;
        }

        Session::flash($ok ? 'success' : 'error', $msg);
        header('Location: ' . $bp . '/orders');
    }
}
