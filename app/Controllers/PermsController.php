<?php

namespace App\Controllers;

use App\Core\TwigService;
use App\Core\Csrf;
use App\Core\Session;
use App\Models\Perm;

class PermsController
{
    private function ensureAdmin(): void
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u || ($u['role'] ?? '') !== 'admin') {
            Session::flash('error', 'غير مصرح');
            $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
            header("Location: {$admin}/dashboard");
            exit;
        }
    }

    // ---------------------------------------------------------------
    // LIST
    // ---------------------------------------------------------------
    public function list(): void
    {
        $this->ensureAdmin();
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        $type = $_GET['type'] ?? '';

        // Perm::all يجهّز options_arr لو كانت val_type = select
        $rows = Perm::all($type ?: null);

        echo TwigService::view()->render('perms/list.twig', [
            'base' => $admin . '/perms',
            'rows' => $rows,
            'type' => $type,
            'succ' => Session::flash('success'),
            'errs' => Session::flash('errors'),
        ]);
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------
    public function create(): void
    {
        $this->ensureAdmin();
        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        echo TwigService::view()->render('perms/create.twig', [
            'base' => $admin . '/perms',
            'errs' => Session::flash('errors'),
        ]);
    }

    // ---------------------------------------------------------------
    // STORE
    // ---------------------------------------------------------------
    public function store(): void
    {
        $this->ensureAdmin();
        Csrf::check($_POST['_csrf'] ?? null);

        $admin    = $_SERVER['BASE_PATH'] ?? '/admincp';
        $type     = trim($_POST['type'] ?? 'app_config');
        $key      = trim($_POST['key']  ?? '');
        $val_type = trim($_POST['val_type'] ?? 'text');
        $status   = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $desc     = trim($_POST['desc'] ?? '');

        // options تأتي من create.twig في حقل باسم "options"
        $options = '';
        if ($val_type === 'select') {
            $options = trim($_POST['options'] ?? '');
            if ($options === '') {
                // لو ما فيه خيارات، نحفظ مصفوفة فاضية
                $options = '[]';
            }
        }

        // بناء قيمة value حسب نوع القيمة
        $value = '';

        if ($val_type === 'switch') {
            $value = isset($_POST['value_switch']) ? '1' : '0';
        } elseif ($val_type === 'text') {
            $value = (string)($_POST['value_text'] ?? '');
        } elseif ($val_type === 'textarea') {
            $value = (string)($_POST['value_textarea'] ?? '');
        } elseif ($val_type === 'select') {
            $value = (string)($_POST['value_select'] ?? '');
        } elseif ($val_type === 'image') {
            $value = $this->uploadImage($_FILES['value_image'] ?? null);
        }

        // يمكن إضافة تحقق بسيط على المفتاح
        if (!preg_match('~^[a-z0-9._-]{1,100}$~', $key)) {
            Session::flash('errors', 'صيغة المفتاح غير صالحة.');
            header("Location: {$admin}/perms/create");
            exit;
        }

        // منع التكرار
        if (Perm::exists($key)) {
            Session::flash('errors', 'هذا المفتاح موجود مسبقاً.');
            header("Location: {$admin}/perms/create");
            exit;
        }

        Perm::setRow($key, [
            'type'     => $type,
            'desc'     => $desc,
            'value'    => $value,
            'val_type' => $val_type,
            'status'   => $status,
            'options'  => $options,
        ]);

        Session::flash('success', 'تمت الإضافة بنجاح.');
        header("Location: {$admin}/perms?type={$type}");
        exit;
    }

    // ---------------------------------------------------------------
    // EDIT
    // ---------------------------------------------------------------
public function edit(string $key): void
{
    $this->ensureAdmin();
    $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

    $row = Perm::getRow($key);

    if (!$row) {
        http_response_code(404);
        exit('العنصر غير موجود');
    }

    // --- جلب الفروع (وليس المتاجر) ---
    $branches = \App\Models\Branch::listAll();

    // --- جلب الفروع المحظورة ---
    $blockedIds = $row['blocked_IDs']
        ? json_decode($row['blocked_IDs'], true)
        : [];

    $options = $row['options_arr'] ?? [];

    echo TwigService::view()->render('perms/edit.twig', [
        'base'        => $admin . '/perms',
        'row'         => $row,
        'options'     => $options,
        'errs'        => Session::flash('errors'),
        'branches'    => $branches,      // ← مهم جداً
        'blocked_ids' => $blockedIds,    // ← مهم جداً
    ]);
}



    // ---------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------
    public function update(string $key): void
    {
        $this->ensureAdmin();
        Csrf::check($_POST['_csrf'] ?? null);

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';
        $row   = Perm::getRow($key);
        if (!$row) {
            http_response_code(404);
            exit('العنصر غير موجود');
        }

        $type     = $row['type'];
        $val_type = $row['val_type'];

        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $desc   = trim($_POST['desc'] ?? '');

        // options إذا كان النوع select فقط
        $options = $row['options'];
        if ($val_type === 'select') {
            $options = trim($_POST['options'] ?? '');
            if ($options === '') {
                $options = '[]';
            }
        }

        // القيمة
        $value = $row['value'];

        if ($val_type === 'switch') {
            $value = isset($_POST['value_switch']) ? '1' : '0';
        } elseif ($val_type === 'text') {
            $value = (string)($_POST['value_text'] ?? '');
        } elseif ($val_type === 'textarea') {
            $value = (string)($_POST['value_textarea'] ?? '');
        } elseif ($val_type === 'select') {
            $value = (string)($_POST['value_select'] ?? '');
        } elseif ($val_type === 'image') {
            $value = $this->uploadImage($_FILES['value_image'] ?? null, $row['value']);
        }

        // --- حفظ المتاجر الممنوعة ---
        if (isset($_POST['blocked_IDs']) && is_array($_POST['blocked_IDs'])) {
    $blocked = array_map('intval', $_POST['blocked_IDs']);
    $blockedJson = json_encode($blocked, JSON_UNESCAPED_UNICODE);
} else {
    $blockedJson = '[]';
}


        Perm::setRow($key, [
    'type'        => $type,
    'desc'        => $desc,
    'value'       => $value,
    'val_type'    => $val_type,
    'status'      => $status,
    'options'     => $options,
    'blocked_IDs' => $blockedJson
]);


        Session::flash('success', 'تم الحفظ بنجاح.');
        header("Location: {$admin}/perms?type={$type}");
        exit;
    }

    // ---------------------------------------------------------------
    // DELETE (اختياري لو كنت تستخدمه)
    // ---------------------------------------------------------------
    public function destroy(string $key): void
    {
        $this->ensureAdmin();
        Csrf::check($_POST['_csrf'] ?? null);

        $admin = $_SERVER['BASE_PATH'] ?? '/admincp';

        $row = Perm::getRow($key);
        if ($row) {
            Perm::delete($key);
            Session::flash('success', 'تم حذف الإعداد.');
            header("Location: {$admin}/perms?type={$row['type']}");
        } else {
            Session::flash('errors', 'العنصر غير موجود.');
            header("Location: {$admin}/perms");
        }
        exit;
    }

    // ---------------------------------------------------------------
    // رفع صورة
    // ---------------------------------------------------------------
    private function uploadImage(?array $file, string $old = ''): string
    {
        if (!$file || empty($file['name'])) {
            return $old;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return $old;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
            return $old;
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB
            return $old;
        }

        $uploads = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads';
        if (!is_dir($uploads)) {
            @mkdir($uploads, 0775, true);
        }

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
        $name = time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $uploads . '/' . $name)) {
            return $old;
        }

        return $name;
    }
}
