<?php
namespace App\Models;

use App\Core\DB;
use PDO;

final class Order
{
    /**
     * جلب طلب واحد
     */
    public static function findById(int $id): ?array
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * حذف طلب
     */
    public static function deleteById(int $id): void
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare('DELETE FROM orders WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    /**
     * ترقيم + فلاتر + فرز آمن
     * ملاحظة: لا يوجد market_id في جدول orders → لذلك تم استبداله بـ branch_id
     */
    public static function paginate(
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
        array $sort = [],
        ?int $forceBranchId = null
    ): array {
        $pdo = DB::pdo();

        $where = [];
        $params = [];

        // فلترة الحالة
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        // فلترة حالة الدفع
        if (!empty($filters['pay_status'])) {
            $where[] = 'pay_status = :pay_status';
            $params['pay_status'] = $filters['pay_status'];
        }

        // نوع الدفع
        if (!empty($filters['pay_type'])) {
            $where[] = 'pay_type = :pay_type';
            $params['pay_type'] = $filters['pay_type'];
        }

        // فلترة المستخدم
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = (int)$filters['user_id'];
        }

        // فلترة الفروع
        if (!empty($filters['branch_id'])) {
            $where[] = 'branch_id = :branch_id';
            $params['branch_id'] = (int)$filters['branch_id'];
        }

        // إلزام الفلترة على فرع معيّن
        if ($forceBranchId !== null) {
            $where[] = 'branch_id = :forced_branch';
            $params['forced_branch'] = (int)$forceBranchId;
        }

        // WHERE النهائي
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // فرز آمن — لا تستخدم market_id لأنه غير موجود
        $allowedFields = ['id', 'created_at', 'total', 'status', 'pay_status', 'pay_type', 'user_id', 'branch_id'];

        $field = $sort['field'] ?? 'created_at';
        $field = in_array($field, $allowedFields, true) ? $field : 'created_at';

        $dir = strtolower($sort['dir'] ?? 'desc');
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

        $orderBy = "ORDER BY {$field} {$dir}";

        // عدد الصفوف
        $countSql = "SELECT COUNT(*) FROM orders {$whereSql}";
        $st = $pdo->prepare($countSql);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        // البيانات
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
