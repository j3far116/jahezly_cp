<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class BranchProduct
{
    /* =======================================================
     * 1) البحث عن تخصيص منتج لفرع (كما هو – بدون تغيير)
     * ======================================================= */

    public static function findByBranchProduct(int $branchId, int $productId): ?array
    {
        $st = DB::pdo()->prepare("
            SELECT *
            FROM branch_products
            WHERE branch_id = ? AND product_id = ?
            LIMIT 1
        ");
        $st->execute([$branchId, $productId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =======================================================
     * 2) حفظ / تحديث تخصيص فرع (Override)
     * ======================================================= */

    public static function saveOrUpdate(int $branch_id, int $product_id, array $v): void
    {
        $pdo = DB::pdo();

        // هل يوجد تخصيص سابق؟
        $check = $pdo->prepare("
            SELECT id
            FROM branch_products
            WHERE branch_id = :bid AND product_id = :pid
            LIMIT 1
        ");
        $check->execute([
            ':bid' => $branch_id,
            ':pid' => $product_id
        ]);
        $exists = (bool) $check->fetchColumn();

        if ($exists) {
            // تحديث التخصيص
            $sql = "
                UPDATE branch_products
                SET
                    price = :price,
                    name  = :name,
                    `desc`= :desc,
                    status= :status,
                    updated_at = NOW()
                WHERE branch_id = :bid AND product_id = :pid
            ";
        } else {
            // إنشاء تخصيص جديد
            $sql = "
                INSERT INTO branch_products
                    (branch_id, product_id, price, name, `desc`, status, created_at)
                VALUES
                    (:bid, :pid, :price, :name, :desc, :status, NOW())
            ";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bid'    => $branch_id,
            ':pid'    => $product_id,
            ':price'  => $v['price'] !== '' ? $v['price'] : null,
            ':name'   => trim($v['name'] ?? '') ?: null,
            ':desc'   => trim($v['desc'] ?? '') ?: null,
            ':status' => in_array($v['status'] ?? 'inactive', ['active', 'inactive', 'disabled'], true)
                ? $v['status']
                : 'inactive',
        ]);
    }

    /* =======================================================
     * 3) حذف التخصيص (الرجوع لقيم المتجر)
     * ======================================================= */

    public static function deleteOverride(int $branchId, int $productId): void
    {
        DB::pdo()->prepare("
            DELETE FROM branch_products
            WHERE branch_id = ? AND product_id = ?
        ")->execute([$branchId, $productId]);
    }

    /* =======================================================
     * 4) ⭐ الدالة الأهم: جلب منتجات الفرع (وراثة + Override)
     * ======================================================= */

    public static function listForBranch(int $branchId, int $marketId): array
    {
        $sql = "
            SELECT
                gp.product_id,

                -- الاسم والسعر من الفرع إذا وُجد، وإلا من المتجر
                COALESCE(bp.name, gp.name)   AS name,
                COALESCE(bp.price, gp.price) AS price,
                COALESCE(bp.`desc`, gp.`desc`) AS `desc`,

                -- الحالة: أولوية للفرع
                COALESCE(bp.status, gp.status) AS status

            FROM grocery_products gp

            LEFT JOIN branch_products bp
                ON bp.product_id = gp.product_id
               AND bp.branch_id  = :branch_id

            WHERE gp.market_id = :market_id
              AND COALESCE(bp.status, gp.status) = 'active'

            ORDER BY gp.id ASC
        ";

        $st = DB::pdo()->prepare($sql);
        $st->execute([
            ':branch_id' => $branchId,
            ':market_id' => $marketId
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
