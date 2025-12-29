<?php
namespace App\Models;

use App\Core\DB;
use PDO;

class Cat
{

    public static function allByMarketType(int $market_id, string $type): array
{
    $pdo = DB::pdo();
    $sql = "SELECT * FROM cats
            WHERE market_id = ? 
              AND type = ?
              AND status = 'active'   -- 👈 إخفاء الأقسام المحذوفة
            ORDER BY sort ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$market_id, $type]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}



    public static function find(int $id): ?array
    {
        $st = DB::pdo()->prepare("SELECT id, market_id, name, type FROM cats WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public static function create(array $v): int
    {
        $st = DB::pdo()->prepare(
            "INSERT INTO cats (market_id, name, type) VALUES (:mid, :name, :type)"
        );
        $st->execute([
            ':mid'  => (int)$v['market_id'],
            ':name' => $v['name'],
            ':type' => $v['type'],
        ]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function update(int $id, array $v): bool
    {
        $st = DB::pdo()->prepare("UPDATE cats SET name=:name WHERE id=:id");
        return $st->execute([':name' => $v['name'], ':id' => $id]);
    }

    public static function delete(int $id): bool
    {
        return DB::pdo()->prepare("DELETE FROM cats WHERE id=:id")->execute([':id' => $id]);
    }    public static function forMarketProducts(int $market_id): array
    {
        $pdo = \App\Core\DB::pdo(); // <-- غيّرها لاسم كلاس/دالة الاتصال عندك إن اختلفت
        $sql = "SELECT * FROM cats WHERE market_id = ? AND type = 'products' ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$market_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function softDelete(int $id): bool
{
    $st = DB::pdo()->prepare("UPDATE cats SET status = 'removed' WHERE id = ?");
    return $st->execute([$id]);
}
}
