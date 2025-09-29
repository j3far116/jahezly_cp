<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Location
{
    /** إرجاع المواقع المفعّلة لملء القائمة */
    public static function listActive(): array
    {
        $pdo = DB::pdo();
        $st  = $pdo->query("SELECT id, name FROM locations WHERE status = 'active' ORDER BY name ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** تحقق أن الموقع موجود ومفعّل */
    public static function existsActive(int $id): bool
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare("SELECT 1 FROM locations WHERE id = :id AND status = 'active' LIMIT 1");
        $st->execute(['id' => $id]);
        return (bool)$st->fetchColumn();
    }
}
