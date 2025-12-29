<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Branch
{
    /** =============================
     *  جلب الفروع حسب المتجر
     * ============================= */
    public static function listByMarketId(int $marketId): array
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare(
            "SELECT id, name, location_id, market_id, status, address, created_at
             FROM branches
             WHERE market_id = :mid
             ORDER BY id DESC"
        );
        $st->execute(['mid' => $marketId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** =============================
     *  جلب فرع واحد
     * ============================= */
    public static function findById(int $id): ?array
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare(
            "SELECT id, name, location_id, market_id, status, address, created_at
             FROM branches
             WHERE id = :id
             LIMIT 1"
        );
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** =============================
     *  إنشاء فرع جديد
     * ============================= */
    public static function create(int $marketId, array $v): int
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare(
            "INSERT INTO branches (name, location_id, market_id, status, address)
             VALUES (:name, :location_id, :market_id, :status, :address)"
        );
        $st->execute([
            'name'        => $v['name'],
            'location_id' => (int)$v['location_id'],
            'market_id'   => $marketId,
            'status'      => $v['status'],
            'address'     => $v['address'] !== '' ? $v['address'] : null,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /** =============================
     *  تحديث فرع
     * ============================= */
    public static function updateById(int $id, array $v): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare(
            "UPDATE branches
             SET name = :name,
                 location_id = :location_id,
                 status = :status,
                 address = :address
             WHERE id = :id"
        );
        $st->execute([
            'id'          => $id,
            'name'        => $v['name'],
            'location_id' => (int)$v['location_id'],
            'status'      => $v['status'],
            'address'     => $v['address'] !== '' ? $v['address'] : null,
        ]);
    }

    /** =============================
     *  حذف فرع
     * ============================= */
    public static function deleteById(int $id): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare("DELETE FROM branches WHERE id = :id");
        $st->execute(['id' => $id]);
    }

    /** =============================
     *  جلب فرع مع بيانات الموقع
     * ============================= */
    public static function findWithLocation(int $id): ?array
    {
        $pdo = DB::pdo();
        $sql = "SELECT b.*, l.name AS location_name
                FROM branches b
                LEFT JOIN locations l ON l.id = b.location_id
                WHERE b.id = :id
                LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** =============================
     *  جلب جميع الفروع مع الموقع
     * ============================= */
    public static function listByMarketWithLocation(int $marketId): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT b.*, l.name AS location_name
                FROM branches b
                LEFT JOIN locations l ON l.id = b.location_id
                WHERE b.market_id = :mid
                ORDER BY b.id DESC";

        $st = $pdo->prepare($sql);
        $st->execute(['mid' => $marketId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** =============================
     *  جلب كل الفروع (لوحة التحكم)
     * ============================= */
    public static function listAll(): array
    {
        $pdo = DB::pdo();
        $st  = $pdo->query(
            "SELECT id, name, market_id, status
             FROM branches
             ORDER BY id DESC"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
