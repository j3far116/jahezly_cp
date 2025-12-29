<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Models\GroceryCats;
use App\Models\GroceryGroups;

class GroceryCatsController
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
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            exit('غير مصرح لك بالدخول');
        }
    }

    // -------------------- index --------------------
    public function index()
    {
        $this->adminOnly();

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/cats";

        $cats = GroceryCats::all();
        $groups = GroceryGroups::all();

        echo TwigService::view()->render('grocery_cats/index.twig', [
            'cats'    => $cats,
            'groups'  => $groups,
            'base'    => $base,
            'errs'    => $this->flashGet('errors', []),
            'success' => $this->flashGet('success'),
        ]);
    }

    // -------------------- create --------------------
    public function create()
    {
        $this->adminOnly();

        echo TwigService::view()->render('grocery_cats/create.twig', [
            'groups' => GroceryGroups::all(),
            'base'   => "/admincp/grocery/cats",
            'errs'   => $this->flashGet('errors', []),
            'old'    => $this->flashGet('old', []),
        ]);
    }

    // -------------------- store --------------------
    public function store()
    {
        $this->adminOnly();

        if (!Csrf::check($_POST['_csrf'] ?? null)) exit("CSRF");

        $name     = trim($_POST['name'] ?? '');
        $group_id = intval($_POST['group_id'] ?? 0);
        $status   = $_POST['status'] ?? 'active';

        $errors = [];

        if ($name === '') {
            $errors['name'] = "اسم التصنيف مطلوب";
        }

        if ($group_id === 0) {
            $errors['group_id'] = "يجب اختيار القروب";
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header("Location: /admincp/grocery/cats/create");
            exit;
        }

        GroceryCats::create([
            'name'     => $name,
            'group_id' => $group_id,
            'status'   => $status
        ]);

        header("Location: /admincp/grocery/cats");
        exit;
    }

    // -------------------- edit --------------------
    public function edit(int $id)
    {
        $this->adminOnly();

        $cat = GroceryCats::find($id);
        if (!$cat) exit("Category not found");

        echo TwigService::view()->render('grocery_cats/edit.twig', [
            'row'    => $cat,
            'groups' => GroceryGroups::all(),
            'base'   => "/admincp/grocery/cats",
            'errs'   => $this->flashGet('errors', []),
            'old'    => $this->flashGet('old', []),
        ]);
    }

    // -------------------- update --------------------
    public function update(int $id)
    {
        $this->adminOnly();

        if (!Csrf::check($_POST['_csrf'] ?? null)) exit("CSRF");

        $name     = trim($_POST['name'] ?? '');
        $group_id = intval($_POST['group_id'] ?? 0);
        $status   = $_POST['status'] ?? 'active';

        $errors = [];

        if ($name === '')
            $errors['name'] = "اسم التصنيف مطلوب";

        if ($group_id === 0)
            $errors['group_id'] = "يجب اختيار القروب";

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header("Location: /admincp/grocery/cats/{$id}/edit");
            exit;
        }

        GroceryCats::update($id, [
            'name'     => $name,
            'group_id' => $group_id,
            'status'   => $status,
        ]);

        header("Location: /admincp/grocery/cats");
        exit;
    }

    // -------------------- delete --------------------
    public function delete(int $id)
    {
        $this->adminOnly();

        GroceryCats::delete($id);

        header("Location: /admincp/grocery/cats");
        exit;
    }
}
