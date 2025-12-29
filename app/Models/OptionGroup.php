<?php
namespace App\Models;

use App\Core\DB;
use PDO;

final class OptionGroup
{
    private static string $table = 'options_group';

    public static function find(int $id): ?array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM ".self::$table." WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function allByProduct(int $product_id): array
    {
        // نعرض الأحدث أولًا
        $stmt = DB::pdo()->prepare("SELECT * FROM ".self::$table." WHERE product_id = :pid ORDER BY id DESC");
        $stmt->execute(['pid' => $product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // (الحفظ سنضيفه في الخطوة القادمة)
    // public static function create(array $data): int { ... }
    // public static function update(int $id, array $data): bool { ... }
    // public static function delete(int $id): bool { ... }

public static function create(array $data)
{
    $pdo = \App\Core\DB::pdo();
    $stmt = $pdo->prepare("
        INSERT INTO options_group 
        (product_id, name, type, required, min, max, options, sort_order, available)
        VALUES (:product_id, :name, :type, :required, :min, :max, :options, :sort_order, :available)
    ");
    $stmt->execute([
        ':product_id' => $data['product_id'],
        ':name'       => $data['name'],
        ':type'       => $data['type'],
        ':required'   => $data['required'],
        ':min'        => $data['min'],
        ':max'        => $data['max'],
        ':options'    => $data['options'],
        ':sort_order' => $data['sort_order'] ?? 0,
        ':available'  => $data['available'] ?? 1,
    ]);
    return $pdo->lastInsertId();
}


public static function updateById(int $id, array $data)
{
    $pdo = \App\Core\DB::pdo();
    $stmt = $pdo->prepare("
        UPDATE options_group SET 
        name = :name,
        type = :type,
        required = :required,
        min = :min,
        max = :max,
        available = :available
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'        => $id,
        ':name'      => $data['name'],
        ':type'      => $data['type'],
        ':required'  => $data['required'],
        ':min'       => $data['min'],
        ':max'       => $data['max'],
        ':available' => $data['available'] ?? 1,
    ]);
}


    public static function deleteById(int $id): bool
    {
        $stmt = DB::pdo()->prepare("DELETE FROM " . self::$table . " WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    
    public static function updateOptions(int $id, array $options): bool
{
    // طبّع دائمًا إلى [{id, sort_order}]
    $normalized = [];
    $order = 1;
    foreach ($options as $opt) {
        if (is_array($opt) && isset($opt['id'])) {
            $normalized[] = ['id' => (int)$opt['id'], 'sort_order' => (int)($opt['sort_order'] ?? $order)];
        } else {
            $normalized[] = ['id' => (int)$opt, 'sort_order' => $order];
        }
        $order++;
    }

    $stmt = \App\Core\DB::pdo()->prepare("UPDATE " . self::$table . " SET options = :options WHERE id = :id");
    return $stmt->execute([
        'id'      => $id,
        'options' => json_encode($normalized, JSON_UNESCAPED_UNICODE)
    ]);
}



}
