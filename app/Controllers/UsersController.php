<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Session;
use App\Core\Csrf;
use App\Services\Gate;
use App\Models\User;
use App\Models\Market;

final class UsersController
{
    public function index(): void
    {
        Gate::allow(['admin']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/users';

        // فلاتر GET
        $filters = [
            'q'         => trim((string)($_GET['q'] ?? '')),
            'role'      => (string)($_GET['role'] ?? ''),
            'status'    => (string)($_GET['status'] ?? ''),
            'market_id' => (string)($_GET['market_id'] ?? ''),
        ];

        // جلب بحسب الفلاتر
        $users = User::filter($filters);

        // قائمة المتاجر + خارطة id=>name لاستخدامها في Twig بدون |int
        $markets = Market::all();
        $marketMap = [];
        foreach ($markets as $m) {
            $marketMap[(string)$m['id']] = $m['name'];
        }

        TwigService::refreshGlobals();
        echo TwigService::view()->render('users/index.twig', [
            'base'      => $base,
            'users'     => $users,
            'filters'   => $filters,
            'markets'   => $markets,
            'marketMap' => $marketMap,
        ]);
    }

    public function create(): void
    {
        Gate::allow(['admin']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/users';

        TwigService::refreshGlobals();
        echo TwigService::view()->render('users/create.twig', [
            'base'    => $base,
            'values'  => [
                'name'=>'','email'=>'','mobile'=>'','role'=>'user',
                'status'=>'active','market_id'=>'','password'=>'','confirm'=>''
            ],
            'markets' => Market::all(),
            'errors'  => [],
            '_csrf'   => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        Gate::allow(['admin']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/users';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error','طلب غير صالح.');
            header("Location: {$base}/create"); return;
        }

        $v = [
            'name'      => trim((string)($_POST['name'] ?? '')),
            'email'     => trim((string)($_POST['email'] ?? '')),
            'mobile'    => trim((string)($_POST['mobile'] ?? '')),
            'password'  => trim((string)($_POST['password'] ?? '')),
            'confirm'   => trim((string)($_POST['confirm'] ?? '')),
            'role'      => (string)($_POST['role'] ?? 'user'),
            'status'    => (string)($_POST['status'] ?? 'active'),
            'market_id' => (string)($_POST['market_id'] ?? ''),
        ];

        $errors = $this->validate($v, false);
        if (User::emailExists($v['email'])) {
            $errors['email'] = 'البريد مستخدم مسبقًا.';
        }

        if ($errors) {
            TwigService::refreshGlobals();
            echo TwigService::view()->render('users/create.twig', [
                'base'    => $base,
                'values'  => $v,
                'markets' => Market::all(),
                'errors'  => $errors,
                '_csrf'   => Csrf::token(),
            ]);
            return;
        }

        User::create($v);
        Session::flash('success','تمت الإضافة بنجاح.');
        header("Location: {$base}");
    }

    public function edit(int $id): void
    {
        Gate::allow(['admin']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/users';

        $u = User::findById((int)$id);
        if (!$u) {
            Session::flash('error','المستخدم غير موجود.');
            header("Location: {$base}"); return;
        }

        $u['password'] = '';
        $u['confirm']  = '';

        TwigService::refreshGlobals();
        echo TwigService::view()->render('users/edit.twig', [
            'base'    => $base,
            'values'  => $u,
            'markets' => Market::all(),
            'errors'  => [],
            '_csrf'   => Csrf::token(),
        ]);
    }

    public function update(int $id): void
    {
        Gate::allow(['admin']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/users';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error','طلب غير صالح.');
            header("Location: {$base}/{$id}/edit"); return;
        }

        $v = [
            'name'      => trim((string)($_POST['name'] ?? '')),
            'email'     => trim((string)($_POST['email'] ?? '')),
            'mobile'    => trim((string)($_POST['mobile'] ?? '')),
            'password'  => trim((string)($_POST['password'] ?? '')),
            'confirm'   => trim((string)($_POST['confirm'] ?? '')),
            'role'      => (string)($_POST['role'] ?? 'user'),
            'status'    => (string)($_POST['status'] ?? 'active'),
            'market_id' => (string)($_POST['market_id'] ?? ''),
        ];

        $errors = $this->validate($v, true);
        if (User::emailExists($v['email'], (int)$id)) {
            $errors['email'] = 'البريد مستخدم لمستخدم آخر.';
        }

        if ($errors) {
            $v['id'] = (int)$id;
            TwigService::refreshGlobals();
            echo TwigService::view()->render('users/edit.twig', [
                'base'    => $base,
                'values'  => $v,
                'markets' => Market::all(),
                'errors'  => $errors,
                '_csrf'   => Csrf::token(),
            ]);
            return;
        }

        User::updateById((int)$id, $v);
        Session::flash('success','تم الحفظ بنجاح.');
        header("Location: {$base}");
    }

    public function delete(int $id): void
    {
        Gate::allow(['admin']);

        $bp   = rtrim($_ENV['BASE_PATH'] ?? '/admincp', '/');
        $base = $bp . '/users';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('error','طلب غير صالح.');
            header("Location: {$base}"); return;
        }

        User::deleteById((int)$id);
        Session::flash('success','تم الحذف.');
        header("Location: {$base}");
    }

    private function validate(array $v, bool $isUpdate): array
    {
        $errors = [];

        if ($v['name'] === '' || mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 100) {
            $errors['name'] = 'الاسم مطلوب (2–100).';
        }
        if ($v['email'] === '' || mb_strlen($v['email']) > 100 || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'البريد غير صالح.';
        }
        if ($v['mobile'] === '' || mb_strlen($v['mobile']) > 20) {
            $errors['mobile'] = 'رقم الجوال مطلوب (حتى 20).';
        }
        if (!in_array($v['role'], ['admin','owner','user'], true)) {
            $errors['role'] = 'دور غير صالح.';
        }
        if (!in_array($v['status'], ['active','inactive','blocked','removed'], true)) {
            $errors['status'] = 'حالة غير صالحة.';
        }

        if (!$isUpdate || $v['password'] !== '') {
            if (mb_strlen($v['password']) < 6) {
                $errors['password'] = 'كلمة المرور 6 أحرف على الأقل.';
            }
            if ($v['password'] !== $v['confirm']) {
                $errors['confirm'] = 'تأكيد كلمة المرور غير مطابق.';
            }
        }

        return $errors;
    }
}
