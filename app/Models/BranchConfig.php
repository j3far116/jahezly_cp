<?php
namespace App\Models;

use App\Core\DB;
use PDO;

class BranchConfig
{
    public static function list(int $branch_id): array
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("SELECT `key`, `value` FROM branch_configs WHERE branch_id = ?");
        $stm->execute([$branch_id]);
        $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

        $output = [];
        foreach ($rows as $r) {
            $output[$r['key']] = $r['value'];
        }
        return $output;
    }

    public static function set(int $branch_id, string $key, $value): void
    {
        $pdo = DB::pdo();
        $stm = $pdo->prepare("
            INSERT INTO branch_configs (branch_id, `key`, `value`)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        $stm->execute([$branch_id, $key, $value]);
    }

    public static function deleteAll(int $branch_id): void
    {
        $pdo = DB::pdo();
        $pdo->prepare("DELETE FROM branch_configs WHERE branch_id = ?")
            ->execute([$branch_id]);
    }

    public static function delete(int $branch_id, string $key): void
{
    $pdo = DB::pdo();
    $stm = $pdo->prepare("DELETE FROM branch_configs WHERE branch_id = ? AND `key` = ?");
    $stm->execute([$branch_id, $key]);
}


}
