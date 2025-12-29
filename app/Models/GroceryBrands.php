<?php

namespace App\Models;

use App\Core\DB;
use PDO;

class GroceryBrands
{
    public static function all(): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT * FROM grocery_brands ORDER BY id ASC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT * FROM grocery_brands WHERE id = ?");
        $stm->execute([$id]);
        $row = $stm->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): bool
    {
        $pdo = DB::pdo();
        $sql = "INSERT INTO grocery_brands (name, logo, status)
                VALUES (:name, :logo, :status)";
        $stm = $pdo->prepare($sql);
        return $stm->execute([
            ':name'   => $data['name'],
            ':logo'   => $data['logo'],
            ':status' => $data['status'],
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::pdo();
        $sql = "UPDATE grocery_brands
                SET name = :name, logo = :logo, status = :status
                WHERE id = :id";
        $stm = $pdo->prepare($sql);
        return $stm->execute([
            ':name'   => $data['name'],
            ':logo'   => $data['logo'],
            ':status' => $data['status'],
            ':id'     => $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("DELETE FROM grocery_brands WHERE id = ?");
        return $stm->execute([$id]);
    }
}
