<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Cashier
{
    private const TABLE = 'cashier';

    /* ====== CRUD أساسية ====== */

    public static function create(int $marketId, string $name, string $userCode, string $pinPlain, string $role, string $status, array $branchIds = []): int
    {
        $role   = in_array($role, ['owner','cashier'], true) ? $role : 'cashier';
        $status = in_array($status, ['active','suspended','removed'], true) ? $status : 'active';

        $userCode = self::sanitizeUsername($userCode);
        if ($userCode === '') {
            throw new \InvalidArgumentException('Invalid username');
        }
        if (!self::isUsernameUnique($marketId, $userCode)) {
            throw new \RuntimeException('username already exists');
        }
        if (!self::isValidPin($pinPlain)) {
            throw new \InvalidArgumentException('Invalid PIN');
        }

        $validBranchIds = self::filterValidBranches($marketId, $branchIds);
        $json = $validBranchIds ? json_encode(array_values($validBranchIds), JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO ".self::TABLE."
                (market_id, name, username, password, password_updated_at, role, status, branch_ids)
                VALUES (:mid, :name, :code, :pin, NOW(), :role, :status, :branches)";
        $st = DB::pdo()->prepare($sql);
        $ok = $st->execute([
            ':mid' => $marketId,
            ':name'=> $name,
            ':code'=> $userCode,
            ':pin' => self::hashPin($pinPlain),
            ':role'=> $role,
            ':status'=>$status,
            ':branches'=>$json
        ]);
        if (!$ok) return 0;
        return (int)DB::pdo()->lastInsertId();
    }

    public static function updateById(int $id, string $name, string $userCode, string $role, string $status, array $branchIds = [], ?string $newPin = null): bool
    {
        $cur = self::getById($id);
        if (!$cur) return false;

        $role   = in_array($role, ['owner','cashier'], true) ? $role : $cur['role'];
        $status = in_array($status, ['active','suspended','removed'], true) ? $status : $cur['status'];

        $userCode = self::sanitizeUsername($userCode);
        if ($userCode === '') return false;
        if ($userCode !== $cur['username'] && !self::isUsernameUnique((int)$cur['market_id'], $userCode)) {
            return false;
        }

        $validBranchIds = self::filterValidBranches((int)$cur['market_id'], $branchIds);
        $json = $validBranchIds ? json_encode(array_values($validBranchIds), JSON_UNESCAPED_UNICODE) : null;

        if ($newPin !== null && $newPin !== '') {
            if (!self::isValidPin($newPin)) return false;
            $st = DB::pdo()->prepare("
                UPDATE ".self::TABLE."
                SET name=:name, username=:code, role=:role, status=:status, branch_ids=:branches,
                    password=:pin, password_updated_at=NOW(), updated_at=CURRENT_TIMESTAMP
                WHERE id=:id
            ");
            return $st->execute([
                ':name'=>$name, ':code'=>$userCode, ':role'=>$role, ':status'=>$status,
                ':branches'=>$json, ':pin'=>self::hashPin($newPin), ':id'=>$id
            ]);
        } else {
            $st = DB::pdo()->prepare("
                UPDATE ".self::TABLE."
                SET name=:name, username=:code, role=:role, status=:status, branch_ids=:branches,
                    updated_at=CURRENT_TIMESTAMP
                WHERE id=:id
            ");
            return $st->execute([
                ':name'=>$name, ':code'=>$userCode, ':role'=>$role, ':status'=>$status,
                ':branches'=>$json, ':id'=>$id
            ]);
        }
    }

    public static function getById(int $id): ?array
    {
        $st = DB::pdo()->prepare("SELECT * FROM ".self::TABLE." WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $r['branch_ids'] = self::decodeBranchIds($r['branch_ids'] ?? null);
        return $r;
    }

public static function listForMarket(int $marketId): array
{
    $sql = "SELECT * FROM cashier WHERE market_id=:mid ORDER BY id DESC";
    $st  = \App\Core\DB::pdo()->prepare($sql);
    $st->execute([':mid'=>$marketId]);
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['branch_ids'] = self::decodeBranchIds($r['branch_ids'] ?? null);
    }
    return $rows;
}


    public static function setStatusById(int $id, string $status): bool
    {
        if (!in_array($status, ['active','suspended','removed'], true)) return false;
        $st = DB::pdo()->prepare("UPDATE ".self::TABLE." SET status=:s WHERE id=:id");
        return $st->execute([':s'=>$status, ':id'=>$id]);
    }

    public static function deleteById(int $id): bool
    {
        $st = DB::pdo()->prepare("DELETE FROM ".self::TABLE." WHERE id=?");
        return $st->execute([$id]);
    }

    public static function belongsToMarket(int $id, int $marketId): bool
    {
        $st = DB::pdo()->prepare("SELECT COUNT(1) FROM ".self::TABLE." WHERE id=? AND market_id=?");
        $st->execute([$id, $marketId]);
        return (bool)$st->fetchColumn();
    }

    /* ====== أدوات التحقق والمساعدة ====== */

    public static function sanitizeUsername(string $code): string
    {
        $code = trim($code);
        // المسموح A-Z a-z 0-9 _ -
        if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $code)) return '';
        return $code;
    }

    public static function isUsernameUnique(int $marketId, string $userCode): bool
    {
        $st = DB::pdo()->prepare("SELECT COUNT(1) FROM ".self::TABLE." WHERE market_id=:m AND username=:c");
        $st->execute([':m'=>$marketId, ':c'=>$userCode]);
        return ((int)$st->fetchColumn() === 0);
    }

    public static function isValidPin(string $pin): bool
    {
        // 4-8 أرقام
        return (bool)preg_match('/^[0-9]{4,8}$/', $pin);
    }

    public static function hashPin(string $pin): string
    {
        return password_hash($pin, PASSWORD_BCRYPT);
    }

    private static function filterValidBranches(int $marketId, array $branchIds): array
    {
        if (empty($branchIds)) return [];
        $ids = [];
        foreach ($branchIds as $b) if (ctype_digit((string)$b)) $ids[(int)$b] = true;
        if (!$ids) return [];

        $in = implode(',', array_keys($ids));
        $st = DB::pdo()->prepare("SELECT id FROM branches WHERE market_id=:mid AND id IN ($in)");
        $st->execute([':mid'=>$marketId]);
        $valid = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN, 0) as $bid) $valid[(int)$bid] = true;
        return array_keys($valid);
    }

    private static function decodeBranchIds($json): array
    {
        if (!$json) return [];
        $arr = json_decode((string)$json, true);
        if (!is_array($arr)) return [];
        $out = [];
        foreach ($arr as $v) if (is_int($v) || ctype_digit((string)$v)) $out[(int)$v] = true;
        return array_keys($out);
    }

    // == [ADD] جلب كل الكاشيرات عبر جميع المتاجر + بحث وترقيم
// داخل App\Models\Cashier
public static function listAll(int $limit = 20, int $offset = 0, string $q = ''): array
{
    $pdo   = \App\Core\DB::pdo();
    $table = self::TABLE ?? 'cashiers'; // تأكد أن لديك: private const TABLE = 'cashiers';

    // ضبط الحدود
    $limit  = max(1, $limit);
    $offset = max(0, $offset);

    // فلترة البحث
    $where = "mu.role = 'cashier'";
    $bind  = [];

    if ($q !== '') {
        $where .= " AND (mu.name LIKE :q OR mu.username LIKE :q OR m.name LIKE :q)";
        $bind[':q'] = '%' . $q . '%';
    }

    // إجمالي السجلات
    $sqlCount = "SELECT COUNT(*)
                 FROM {$table} mu
                 JOIN markets m ON m.id = mu.market_id
                 WHERE {$where}";
    $st = $pdo->prepare($sqlCount);
    $st->execute($bind);
    $total = (int)$st->fetchColumn();

    // صفحة البيانات
    $sql = "SELECT mu.*, m.name AS market_name
            FROM {$table} mu
            JOIN markets m ON m.id = mu.market_id
            WHERE {$where}
            ORDER BY mu.id DESC
            LIMIT :lim OFFSET :off";
    $st = $pdo->prepare($sql);
    foreach ($bind as $k => $v) {
        $st->bindValue($k, $v, \PDO::PARAM_STR);
    }
    $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
    $st->bindValue(':off', $offset, \PDO::PARAM_INT);
    $st->execute();

    $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

    // --- تجهيز branch_ids وجلب أسماء الفروع دفعة واحدة ---
    $allBranchIds = [];
    foreach ($rows as &$r) {
        // decode branch_ids (JSON أو فارغ)
        if (isset($r['branch_ids']) && is_string($r['branch_ids']) && $r['branch_ids'] !== '') {
            $dec = json_decode($r['branch_ids'], true);
            $r['branch_ids'] = is_array($dec) ? array_values(array_unique(array_map('intval', $dec))) : [];
        } else {
            $r['branch_ids'] = [];
        }
        // تجميع المعرفات
        foreach ($r['branch_ids'] as $bid) {
            $allBranchIds[$bid] = true;
        }
    }
    unset($r);

    // خريطة معرف→اسم للفروع المستخدمة فقط
    $branchMap = [];
    if (!empty($allBranchIds)) {
        $ids = implode(',', array_map('intval', array_keys($allBranchIds)));
        // ملاحظة: ids آمنة لأننا حوّلناها إلى أعداد صحيحة
        $qB = $pdo->query("SELECT id, name FROM branches WHERE id IN ($ids)");
        $rowsB = $qB ? $qB->fetchAll(\PDO::FETCH_ASSOC) : [];
        foreach ($rowsB as $b) {
            $branchMap[(int)$b['id']] = $b['name'];
        }
    }

    // بناء branch_label لكل سجل
    foreach ($rows as &$r) {
        if (!empty($r['branch_ids'])) {
            $first = (int)$r['branch_ids'][0];
            $label = $branchMap[$first] ?? ('#' . $first);
            $extra = count($r['branch_ids']) - 1;
            $r['branch_label'] = $extra > 0 ? ($label . ' +' . $extra) : $label;
        } else {
            $r['branch_label'] = 'لا يوجد فرع';
        }
    }
    unset($r);

    return [$rows, $total];
}


}
