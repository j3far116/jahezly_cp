<?php
namespace App\Models;

use App\Core\DB;
use PDO;

final class Option
{
    private static string $table = 'options';

    /**
     * جلب جميع الخيارات العامة والخاصة بمنتج معين
     */
    public static function allForMarketAndProduct(int $market_id, int $product_id): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT * FROM options
            WHERE market_id = :mid
              AND (product_id IS NULL OR product_id = :pid)
            ORDER BY id DESC
        ");
        $stmt->execute(['mid' => $market_id, 'pid' => $product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * جلب جميع الخيارات العامة فقط للمتجر
     */
public static function allForMarket(int $market_id): array
{
    $db = DB::pdo();

    $stmt = $db->prepare("
        SELECT 
            o.*,

            /* عدد المنتجات */
            (
                SELECT COUNT(DISTINCT og.product_id)
                FROM options_group og
                JOIN products p ON p.id = og.product_id
                WHERE p.market_id = o.market_id
                  AND JSON_CONTAINS(
                        og.options,
                        JSON_OBJECT('id', o.id)
                  )
            ) AS products_count,

            /* أسماء المنتجات */
            (
                SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR '، ')
                FROM options_group og
                JOIN products p ON p.id = og.product_id
                WHERE p.market_id = o.market_id
                  AND JSON_CONTAINS(
                        og.options,
                        JSON_OBJECT('id', o.id)
                  )
            ) AS products_names

        FROM options o
        WHERE o.market_id = :mid
        ORDER BY o.id ASC
    ");

    $stmt->execute([':mid' => $market_id]);

    return $stmt->fetchAll();
}








    /**
     * إنشاء خيار جديد (عام أو خاص)
     */
    public static function create(array $data): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            INSERT INTO " . self::$table . " (market_id, product_id, name, price, available)
            VALUES (:market_id, :product_id, :name, :price, :available)
        ");
        $stmt->execute([
            'market_id' => $data['market_id'],
            'product_id' => $data['product_id'] ?? null,
            'name' => $data['name'],
            'price' => $data['price'],
            'available' => $data['available'] ?? 1,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * جلب خيار واحد
     */
    public static function find(int $id): ?array
    {
        $stmt = DB::pdo()->prepare("
            SELECT * FROM " . self::$table . " WHERE id = ?
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * تحديث خيار
     */
    public static function updateById(int $id, array $data): bool
    {
        $stmt = DB::pdo()->prepare("
            UPDATE " . self::$table . " 
            SET name = :name, price = :price, available = :available
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'price' => $data['price'],
            'available' => $data['available'] ?? 1,
        ]);
    }

    /**
     * حذف خيار
     */
    public static function deleteById(int $id): bool
    {
        $stmt = DB::pdo()->prepare("
            DELETE FROM " . self::$table . " WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * جلب جميع الخيارات الخاصة بمنتج معين
     */
    public static function allForProduct(int $product_id)
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            SELECT * FROM options WHERE product_id = ? ORDER BY id DESC
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * جلب جميع الخيارات العامة للمتجر
     */
    public static function allForMarketGlobal(int $market_id)
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
        SELECT * FROM options
        WHERE market_id = ? 
          AND (product_id IS NULL OR product_id = 0)
          AND available = 1
        ORDER BY id DESC
        ");
        $stmt->execute([$market_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function isUsedInGroups(int $option_id): bool
{
    $pdo = \App\Core\DB::pdo();

    $stmt = $pdo->prepare("
        SELECT 1
        FROM options_group
        WHERE JSON_CONTAINS(options, :search, '$')
        LIMIT 1
    ");

    $stmt->execute([
        'search' => json_encode(['id' => (int)$option_id])
    ]);

    return (bool) $stmt->fetchColumn();
}

}
