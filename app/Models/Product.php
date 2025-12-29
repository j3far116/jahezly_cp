<?php
namespace App\Models;

use App\Core\DB;
use PDO;

class Product
{
    public static function byMarket(int $marketId): array
    {
        $sql = "SELECT id, market_id, cat_id, status, name, `desc`, price, cover
                FROM products
                WHERE market_id = :mid AND status != 'removed'
                ORDER BY id DESC";
        $st = DB::pdo()->prepare($sql);
        $st->execute([':mid' => $marketId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        $st = DB::pdo()->prepare("SELECT id, market_id, cat_id, status, name, `desc`, price, cover
                                  FROM products WHERE id = :id AND status != 'removed' LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public static function create(array $v): int
    {
        $sql = "INSERT INTO products (market_id, cat_id, status, name, `desc`, price, cover)
                VALUES (:market_id, :cat_id, :status, :name, :desc, :price, :cover)";
        DB::pdo()->prepare($sql)->execute([
            ':market_id' => (int)$v['market_id'],
            ':cat_id'    => (int)$v['cat_id'],
            ':status'    => $v['status'] ?? 'inactive',
            ':name'      => $v['name'],
            ':desc'      => $v['desc'] ?? null,
            ':price'     => $v['price'],
            ':cover'     => $v['cover'] ?? null,
        ]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function update(int $id, array $v): bool
    {
        $sql = "UPDATE products
                SET cat_id=:cat_id, status=:status, name=:name, `desc`=:desc, price=:price
                WHERE id=:id";
        return DB::pdo()->prepare($sql)->execute([
            ':id'     => $id,
            ':cat_id' => (int)$v['cat_id'],
            ':status' => $v['status'],
            ':name'   => $v['name'],
            ':desc'   => $v['desc'] ?? null,
            ':price'  => $v['price'],
        ]);
    }

    public static function delete(int $id): bool
    {
        return DB::pdo()->prepare("DELETE FROM products WHERE id=:id")->execute([':id' => $id]);
    }
public static function updateCover(int $id, ?string $filename): bool
{
    return \App\Core\DB::pdo()
        ->prepare("UPDATE products SET cover=:f WHERE id=:id")
        ->execute([':f' => $filename, ':id' => $id]);
}
    public static function forMarket(int $market_id): array
    {
        $pdo = \App\Core\DB::pdo(); // <-- عدّل اسم موصل الـPDO لو مختلف
        $sql = "SELECT * FROM products WHERE market_id = ? AND status != 'removed' ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$market_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }


    public static function withBranchOverrides(int $marketId, int $branchId): array
{
    $sql = "
        SELECT 
            p.id,
            p.name AS base_name,
            p.price AS base_price,
            p.desc AS base_desc,
            bp.name AS override_name,
            bp.price AS override_price,
            bp.desc AS override_desc,
            bp.status AS override_status
        FROM products p
        LEFT JOIN branch_products bp
            ON bp.product_id = p.id AND bp.branch_id = :bid
        WHERE p.market_id = :mid
        ORDER BY p.id DESC";
    $st = \App\Core\DB::pdo()->prepare($sql);
    $st->execute([':bid' => $branchId, ':mid' => $marketId]);
    return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}

public static function countByCat(int $market_id, int $cat_id): int
{
    $pdo = \App\Core\DB::pdo();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE market_id = ? AND cat_id = ? AND status != 'removed'");
    $stmt->execute([$market_id, $cat_id]);
    return (int)$stmt->fetchColumn();
}


public static function softDelete(int $id): bool
{
    $sql = "UPDATE products SET status = 'removed' WHERE id = :id";
    return DB::pdo()->prepare($sql)->execute([':id' => $id]);
}



}
