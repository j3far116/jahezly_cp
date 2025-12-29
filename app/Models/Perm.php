<?php

namespace App\Models;

use App\Core\DB;
use PDO;

class Perm
{
    /**
     * جلب جميع الإعدادات، مع إمكانية التصفية حسب type
     */
    public static function all(?string $type = null): array
    {
        $pdo = DB::pdo();

        if ($type) {
            $stm = $pdo->prepare("SELECT * FROM perms WHERE `type` = ? ORDER BY `key` ASC");
            $stm->execute([$type]);
            $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $pdo->query("SELECT * FROM perms ORDER BY `key` ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        // تجهيز مصفوفة الخيارات للـ select
        foreach ($rows as &$r) {
            if (($r['val_type'] ?? '') === 'select' && !empty($r['options'])) {
                $arr = json_decode($r['options'], true);
                $r['options_arr'] = is_array($arr) ? $arr : [];
            } else {
                $r['options_arr'] = [];
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * جلب إعداد واحد حسب المفتاح
     */
    public static function getRow(string $key): ?array
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT * FROM perms WHERE `key` = ?");
        $stm->execute([$key]);
        $row = $stm->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (($row['val_type'] ?? '') === 'select' && !empty($row['options'])) {
            $arr = json_decode($row['options'], true);
            $row['options_arr'] = is_array($arr) ? $arr : [];
        } else {
            $row['options_arr'] = [];
        }

        return $row;
    }

    /**
     * هل المفتاح موجود؟
     */
    public static function exists(string $key): bool
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT COUNT(*) FROM perms WHERE `key` = ?");
        $stm->execute([$key]);
        return (bool)$stm->fetchColumn();
    }

    /**
     * حذف إعداد
     */
    public static function delete(string $key): void
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("DELETE FROM perms WHERE `key` = ?");
        $stm->execute([$key]);
    }

    /**
     * إدخال/تحديث إعداد
     *
     * $data = [
     *   'type'     => string,
     *   'desc'     => string,
     *   'value'    => string,
     *   'val_type' => string,
     *   'status'   => string,
     *   'options'  => string (JSON) أو ''
     * ]
     */
public static function setRow(string $key, array $data): void
{
    $pdo = DB::pdo();

    // تحقق هل المفتاح موجود مسبقاً
    $exists = self::exists($key);

    if ($exists) {
        // تحديث
        $sql = "UPDATE perms SET
            `type`        = :type,
            `desc`        = :desc,
            `value`       = :value,
            `val_type`    = :val_type,
            `status`      = :status,
            `options`     = :options,
            `blocked_IDs` = :blocked_IDs
            WHERE `key`   = :key";
    } else {
        // إدخال جديد — بدون created_at ولا updated_at
        $sql = "INSERT INTO perms (
            `key`, `type`, `desc`, `value`, `val_type`,
            `status`, `options`, `blocked_IDs`
        ) VALUES (
            :key, :type, :desc, :value, :val_type,
            :status, :options, :blocked_IDs
        )";
    }

    $st = $pdo->prepare($sql);
    $st->execute([
        ':key'         => $key,
        ':type'        => $data['type'],
        ':desc'        => $data['desc'],
        ':value'       => $data['value'],
        ':val_type'    => $data['val_type'],
        ':status'      => $data['status'],
        ':options'     => $data['options'],
        ':blocked_IDs' => $data['blocked_IDs'] ?? '[]',
    ]);
}




    public static function findByKey(string $key): ?array
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT * FROM perms WHERE `key` = ?");
        $stm->execute([$key]);
        return $stm->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function updateByKey(string $key, array $values): void
    {
        $pdo = DB::pdo();

        $sql = "UPDATE perms SET 
                value = ?, 
                `desc` = ?, 
                options = ?, 
                blocked_IDs = ?
            WHERE `key` = ?";

        $stm = $pdo->prepare($sql);
        $stm->execute([
            $values['value'] ?? '',
            $values['desc'] ?? '',
            $values['options'] ?? '',
            $values['blocked_IDs'] ?? '[]',
            $key
        ]);
    }
}
