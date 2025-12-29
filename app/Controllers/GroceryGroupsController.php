<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Models\GroceryGroups;

class GroceryGroupsController
{
    private function flashGet(string $key, $default = null)
    {
        $k = '_flash_' . $key;
        $v = $_SESSION[$k] ?? $default;
        if (isset($_SESSION[$k])) {
            unset($_SESSION[$k]);
        }
        return $v;
    }

    private function flashSet(string $key, $value): void
    {
        $_SESSION['_flash_' . $key] = $value;
    }

    private function adminOnly(): void
    {
        $user = $_SESSION['user'] ?? null;

        if (!$user || ($user['role'] ?? null) !== 'admin') {
            http_response_code(403);
            exit('غير مصرح لك بالدخول');
        }
    }

    // ======================= index =======================
    public function index(): void
    {
        $this->adminOnly();

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/groups";

        $groups = GroceryGroups::all();

        echo TwigService::view()->render('grocery_groups/index.twig', [
            'groups'  => $groups,
            'base'    => $base,
            'errs'    => $this->flashGet('errors', []),
            'success' => $this->flashGet('success'),
        ]);
    }

    // ======================= create =======================
    public function create(): void
    {
        $this->adminOnly();

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/groups";

        echo TwigService::view()->render('grocery_groups/create.twig', [
            'base' => $base,
            'errs' => $this->flashGet('errors', []),
            'old'  => $this->flashGet('old', []),
        ]);
    }

    // ======================= store =======================
    public function store(): void
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
            $errors['name'] = 'اسم القروب مطلوب';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'] = 'الاسم أطول من المسموح (255 حرفًا)';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header('Location: /admincp/grocery/groups/create');
            exit;
        }

        GroceryGroups::create([
            'name'   => $name,
            'status' => $status,
        ]);

        $this->flashSet('success', 'تمت إضافة القروب بنجاح.');
        header('Location: /admincp/grocery/groups');
        exit;
    }

    // ======================= edit =======================
    public function edit(int $id): void
    {
        $this->adminOnly();

        $group = GroceryGroups::find($id);
        if (!$group) {
            http_response_code(404);
            exit('Group not found');
        }

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/groups";

        echo TwigService::view()->render('grocery_groups/edit.twig', [
            'row'  => $group,
            'base' => $base,
            'errs' => $this->flashGet('errors', []),
            'old'  => $this->flashGet('old', []),
        ]);
    }

    // ======================= update =======================
    public function update(int $id): void
    {
        $this->adminOnly();

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('CSRF');
        }

        $group = GroceryGroups::find($id);
        if (!$group) {
            http_response_code(404);
            exit('Group not found');
        }

        $name   = trim($_POST['name'] ?? '');
        $status = $_POST['status'] ?? 'active';

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'اسم القروب مطلوب';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'] = 'الاسم أطول من المسموح (255 حرفًا)';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header("Location: /admincp/grocery/groups/{$id}/edit");
            exit;
        }

        GroceryGroups::update($id, [
            'name'   => $name,
            'status' => $status,
        ]);

        $this->flashSet('success', 'تم تحديث القروب بنجاح.');
        header('Location: /admincp/grocery/groups');
        exit;
    }

    // ======================= delete =======================
    public function delete(int $id): void
    {
        $this->adminOnly();

        $group = GroceryGroups::find($id);
        if ($group) {
            GroceryGroups::delete($id);
            $this->flashSet('success', 'تم حذف القروب بنجاح.');
        }

        header('Location: /admincp/grocery/groups');
        exit;
    }
}
