<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class BranchProduct
{
    public static function findByBranchProduct(int $branchId, int $productId): ?array
    {
        $st = DB::pdo()->prepare("SELECT * FROM branch_products WHERE branch_id=? AND product_id=? LIMIT 1");
        $st->execute([$branchId, $productId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

public static function saveOrUpdate(int $branch_id, int $product_id, array $v): void
{
    $pdo = \App\Core\DB::pdo();

    // التحقق إن كان السجل موجود مسبقًا
    $check = $pdo->prepare("SELECT id FROM branch_products WHERE branch_id=:bid AND product_id=:pid LIMIT 1");
    $check->execute([':bid' => $branch_id, ':pid' => $product_id]);
    $exists = (bool) $check->fetchColumn();

    if ($exists) {
        // ✅ تحديث السجل الحالي
        $sql = "UPDATE branch_products 
                SET 
                    price   = :price,
                    name    = :name,
                    `desc`  = :desc,
                    status  = :status,
                    updated_at = NOW()
                WHERE branch_id = :bid AND product_id = :pid";
    } else {
        // ✅ إنشاء سجل جديد
        $sql = "INSERT INTO branch_products 
                    (branch_id, product_id, price, name, `desc`, status, created_at)
                VALUES
                    (:bid, :pid, :price, :name, :desc, :status, NOW())";
    }

    $stmt = $pdo->prepare($sql);
$stmt->execute([
    ':bid'    => $branch_id,
    ':pid'    => $product_id,
    ':price'  => $v['price'] !== '' ? $v['price'] : null,
    ':name'   => trim($v['name'] ?? '') ?: null,
    ':desc'   => trim($v['desc'] ?? '') ?: null,

    // ✅ تصحيح القيمة المرسلة لحقل status
    ':status' => in_array($v['status'] ?? 'inactive', ['active', 'inactive', 'disabled'], true)
        ? $v['status']
        : 'inactive',
]);
}


    public static function deleteOverride(int $branchId, int $productId): void
    {
        DB::pdo()->prepare("DELETE FROM branch_products WHERE branch_id=? AND product_id=?")->execute([$branchId, $productId]);
    }
}
