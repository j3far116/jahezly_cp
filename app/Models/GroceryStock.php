<?php

namespace App\Models;

use App\Core\DB;
use PDO;

class GroceryStock
{
    public static function all()
    {
        $pdo = DB::pdo();
        $stm = $pdo->query("SELECT * FROM grocery_stock ORDER BY id DESC");
        return $stm->fetchAll(PDO::FETCH_ASSOC);
    }

public static function allWithCats()
{
    $pdo = DB::pdo();
    $sql = "
        SELECT
            gs.*,
            gc.name AS cat_name,
            gg.name AS group_name,
            gb.name AS brand_name,
            gu.name AS unit_name,
            gu.abbreviation AS unit_abbr
        FROM grocery_stock gs
        LEFT JOIN grocery_cats gc    ON gc.id = gs.cat_id
        LEFT JOIN grocery_groups gg  ON gg.id = gc.group_id
        LEFT JOIN grocery_brands gb  ON gb.id = gs.brand_id
        LEFT JOIN grocery_units gu   ON gu.id = gs.unit_id
        ORDER BY gs.id DESC
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}


    public static function find(int $id)
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT * FROM grocery_stock WHERE id = ?");
        $stm->execute([$id]);
        return $stm->fetch(PDO::FETCH_ASSOC);
    }

public static function create(array $data)
{
    $pdo = DB::pdo();
    $sql = "
        INSERT INTO grocery_stock (name, barcode, brand_id, unit_id, cat_id, size, image, status)
        VALUES (:name, :barcode, :brand_id, :unit_id, :cat_id, :size, :image, :status)
    ";
    $stm = $pdo->prepare($sql);
    return $stm->execute($data);
}


public static function update(int $id, array $data)
{
    $pdo = DB::pdo();

    // قائمة الحقول المسموح بتحديثها
    $allowed = [
        'name', 'barcode', 'brand_id', 'unit_id',
        'cat_id', 'size', 'image', 'status'
    ];

    // فلترة البيانات — منع أي مفتاح غير مسموح
    $clean = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed, true)) {
            $clean[$key] = $value;
        }
    }

    if (empty($clean)) {
        return false; // لا يوجد شيء لتحديثه
    }

    // بناء الحقول
    $fields = [];
    foreach ($clean as $key => $value) {
        $fields[] = "$key = :$key";
    }

    $sql = "UPDATE grocery_stock SET " . implode(", ", $fields) . " WHERE id = :id";

    // إضافة id للمعاملات
    $clean['id'] = $id;

    $stm = $pdo->prepare($sql);
    return $stm->execute($clean);
}


    public static function delete(int $id)
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("DELETE FROM grocery_stock WHERE id = ?");
        return $stm->execute([$id]);
    }
}
