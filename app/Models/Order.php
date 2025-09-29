<?php
namespace App\Models;

use App\Core\DB;
use PDO;

final class Order
{
    public static function findById(int $id): ?array
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function deleteById(int $id): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare('DELETE FROM orders WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    /**
     * ترقيم + فلاتر + فرز آمن (whitelist)
     * $filters = ['status'=>..., 'pay_status'=>..., 'pay_type'=>..., 'market_id'=>..., 'user_id'=>...]
     * $sort = ['field'=>'created_at','dir'=>'desc']
     */
    public static function paginate(
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
        array $sort = [],
        ?int $forceMarketId = null
    ): array {
        $pdo = DB::pdo();

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['pay_status'])) {
            $where[] = 'pay_status = :pay_status';
            $params['pay_status'] = $filters['pay_status'];
        }
        if (!empty($filters['pay_type'])) {
            $where[] = 'pay_type = :pay_type';
            $params['pay_type'] = $filters['pay_type'];
        }
        if (!empty($filters['market_id'])) {
            $where[] = 'market_id = :market_id';
            $params['market_id'] = (int)$filters['market_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = (int)$filters['user_id'];
        }

        // market_id: إن كان مُجبراً => تجاهل ما في $filters واستخدم $forceMarketId
if ($forceMarketId !== null) {
    $where[] = 'market_id = :market_id';
    $params['market_id'] = $forceMarketId;
} elseif (!empty($filters['market_id'])) {
    $where[] = 'market_id = :market_id';
    $params['market_id'] = (int)$filters['market_id'];
}

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // فرز آمن
        $allowedFields = ['id','created_at','total','status','pay_status','pay_type','market_id','user_id'];
        $field = in_array(($sort['field'] ?? ''), $allowedFields, true) ? $sort['field'] : 'created_at';
        $dir   = strtolower($sort['dir'] ?? 'desc');
        $dir   = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';
        $orderBy = "ORDER BY {$field} {$dir}";

        // عدّاد
        $countSql = "SELECT COUNT(*) FROM orders {$whereSql}";
        $st = $pdo->prepare($countSql);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        // بيانات
        $sql = "SELECT * FROM orders {$whereSql} {$orderBy} LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'        => $rows,
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
        ];
    }
}
