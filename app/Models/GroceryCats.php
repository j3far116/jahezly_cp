<?php

namespace App\Models;

use App\Core\DB;
use PDO;

class GroceryCats
{
    public static function all(): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT c.*, g.name AS group_name 
                FROM grocery_cats c
                LEFT JOIN grocery_groups g ON g.id = c.group_id
                ORDER BY c.id ASC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function byGroup(int $gid): array
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT * FROM grocery_cats WHERE group_id = ? ORDER BY id ASC");
        $stm->execute([$gid]);
        return $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT * FROM grocery_cats WHERE id = ?");
        $stm->execute([$id]);
        $row = $stm->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): bool
    {
        $pdo = DB::pdo();
        $sql = "INSERT INTO grocery_cats (name, group_id, status)
                VALUES (:name, :group_id, :status)";
        $stm = $pdo->prepare($sql);
        return $stm->execute([
            ':name'     => $data['name'],
            ':group_id' => $data['group_id'],
            ':status'   => $data['status'],
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::pdo();
        $sql = "UPDATE grocery_cats 
                SET name = :name, group_id = :group_id, status = :status
                WHERE id = :id";
        $stm = $pdo->prepare($sql);

        return $stm->execute([
            ':name'     => $data['name'],
            ':group_id' => $data['group_id'],
            ':status'   => $data['status'],
            ':id'       => $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("DELETE FROM grocery_cats WHERE id = ?");
        return $stm->execute([$id]);
    }
}
