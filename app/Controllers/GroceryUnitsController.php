<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Models\GroceryUnits;

class GroceryUnitsController
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

    // --------------------------- index ---------------------------
    public function index()
    {
        $this->adminOnly();

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $base  = "$admin/grocery/units";

        $rows = GroceryUnits::all();

        echo TwigService::view()->render('grocery_units/index.twig', [
            'units'   => $rows,
            'base'    => $base,
            'errs'    => $this->flashGet('errors', []),
            'success' => $this->flashGet('success'),
        ]);
    }

    // --------------------------- create ---------------------------
    public function create()
    {
        $this->adminOnly();

        echo TwigService::view()->render('grocery_units/create.twig', [
            'base' => "/admincp/grocery/units",
            'errs' => $this->flashGet('errors', []),
            'old'  => $this->flashGet('old', []),
        ]);
    }

    // --------------------------- store ---------------------------
    public function store()
    {
        $this->adminOnly();

        if (!Csrf::check($_POST['_csrf'] ?? null)) exit("CSRF");

        $name         = trim($_POST['name'] ?? '');
        $abbreviation = trim($_POST['abbreviation'] ?? '');
        $status       = $_POST['status'] ?? 'active';

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'اسم الوحدة مطلوب';
        }

        if ($abbreviation === '') {
            $errors['abbreviation'] = 'الاختصار مطلوب (مثل kg, L, pcs)';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header("Location: /admincp/grocery/units/create");
            exit;
        }

        GroceryUnits::create([
            'name'         => $name,
            'abbreviation' => $abbreviation,
            'status'       => $status,
        ]);

        header("Location: /admincp/grocery/units");
        exit;
    }

    // --------------------------- edit ---------------------------
    public function edit(int $id)
    {
        $this->adminOnly();

        $row = GroceryUnits::find($id);
        if (!$row) exit("Unit not found");

        echo TwigService::view()->render('grocery_units/edit.twig', [
            'row'  => $row,
            'base' => "/admincp/grocery/units",
            'errs' => $this->flashGet('errors', []),
            'old'  => $this->flashGet('old', []),
        ]);
    }

    // --------------------------- update ---------------------------
    public function update(int $id)
    {
        $this->adminOnly();

        if (!Csrf::check($_POST['_csrf'] ?? null)) exit("CSRF");

        $name         = trim($_POST['name'] ?? '');
        $abbreviation = trim($_POST['abbreviation'] ?? '');
        $status       = $_POST['status'] ?? 'active';

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'اسم الوحدة مطلوب';
        }

        if ($abbreviation === '') {
            $errors['abbreviation'] = 'الاختصار مطلوب';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $_POST);
            header("Location: /admincp/grocery/units/{$id}/edit");
            exit;
        }

        GroceryUnits::update($id, [
            'name'         => $name,
            'abbreviation' => $abbreviation,
            'status'       => $status,
        ]);

        header("Location: /admincp/grocery/units");
        exit;
    }

    // --------------------------- delete ---------------------------
    public function delete(int $id)
    {
        $this->adminOnly();

        GroceryUnits::delete($id);

        header("Location: /admincp/grocery/units");
        exit;
    }
}
