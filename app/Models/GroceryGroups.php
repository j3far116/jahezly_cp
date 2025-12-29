<?php

namespace App\Models;

use App\Core\DB;
use PDO;

class GroceryGroups
{
    public static function all(): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT * FROM grocery_groups ORDER BY id ASC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT * FROM grocery_groups WHERE id = ?");
        $stm->execute([$id]);
        $row = $stm->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): bool
    {
        $pdo = DB::pdo();
        $sql = "INSERT INTO grocery_groups (name, status) VALUES (:name, :status)";
        $stm = $pdo->prepare($sql);
        return $stm->execute([
            ':name'   => $data['name'],
            ':status' => $data['status'],
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::pdo();
        $sql = "UPDATE grocery_groups SET name = :name, status = :status WHERE id = :id";
        $stm = $pdo->prepare($sql);
        return $stm->execute([
            ':name'   => $data['name'],
            ':status' => $data['status'],
            ':id'     => $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("DELETE FROM grocery_groups WHERE id = ?");
        return $stm->execute([$id]);
    }
}
