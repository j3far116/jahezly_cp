<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Market
{
    public static function all(): array
    {
        $pdo = DB::pdo();
        $st  = $pdo->query("SELECT id, name, `desc`, cover, logo, status, created_at FROM markets ORDER BY id DESC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findById(int $id): ?array
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare("SELECT id, name, `desc`, cover, logo, status, created_at FROM markets WHERE id = :id LIMIT 1");
        $st->execute(['id'=>$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function listForScope(?int $scopedMarketId): array
    {
        if ($scopedMarketId === null) return self::all();
        $one = self::findById($scopedMarketId);
        return $one ? [$one] : [];
    }

    public static function create(array $v): int
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare(
            "INSERT INTO markets (name, `desc`, cover, logo, status)
             VALUES (:name, :desc, :cover, :logo, :status)"
        );
        $st->execute([
            'name'   => $v['name'],
            'desc'   => ($v['desc'] !== '' ? $v['desc'] : null),
            'cover'  => ($v['cover'] !== '' ? $v['cover'] : null),
            'logo'   => ($v['logo']  !== '' ? $v['logo']  : null),
            'status' => $v['status'],
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateById(int $id, array $v): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare(
            "UPDATE markets
             SET name = :name,
                 `desc` = :desc,
                 cover = :cover,
                 logo  = :logo,
                 status = :status
             WHERE id = :id"
        );
        $st->execute([
            'id'     => $id,
            'name'   => $v['name'],
            'desc'   => ($v['desc'] !== '' ? $v['desc'] : null),
            'cover'  => ($v['cover'] !== '' ? $v['cover'] : null),
            'logo'   => ($v['logo']  !== '' ? $v['logo']  : null),
            'status' => $v['status'],
        ]);
    }

    public static function deleteById(int $id): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare("DELETE FROM markets WHERE id = :id");
        $st->execute(['id'=>$id]);
    }

    public static function updateCover(int $id, string $filename): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare("UPDATE markets SET cover = :c WHERE id = :id");
        $st->execute(['c'=>$filename,'id'=>$id]);
    }

    public static function updateLogo(int $id, string $filename): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare("UPDATE markets SET logo = :l WHERE id = :id");
        $st->execute(['l'=>$filename,'id'=>$id]);
    }
}
