<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class User
{
    // ====== لقائمة المستخدمين والعرض (بدون كلمة المرور) ======
    public static function all(): array
    {
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.role, u.status,
                       u.market_id, u.created_at, m.name AS market_name
                FROM users u
                LEFT JOIN markets m ON m.id = u.market_id
                ORDER BY u.id DESC";
        $st = DB::pdo()->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.role, u.status,
                       u.market_id, u.created_at, m.name AS market_name
                FROM users u
                LEFT JOIN markets m ON m.id = u.market_id
                WHERE u.id = ?
                LIMIT 1";
        $st = DB::pdo()->prepare($sql);
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    // ====== لازمة لتسجيل الدخول ======
    public static function findByEmail(string $email): ?array
    {
        $st = DB::pdo()->prepare(
            "SELECT id, name, email, mobile, password, role, status, market_id, token, created_at
             FROM users
             WHERE email = ?
             LIMIT 1"
        );
        $st->execute([$email]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    // ====== تذكرني (النسخ الرئيسية) ======
    public static function findByRememberTokenHash(string $hash): ?array
    {
        $st = DB::pdo()->prepare(
            "SELECT id, name, email, mobile, role, status, market_id, token, created_at
             FROM users
             WHERE token = ?
             LIMIT 1"
        );
        $st->execute([$hash]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public static function updateRememberTokenHash(int $userId, ?string $hash): bool
    {
        $st = DB::pdo()->prepare("UPDATE users SET token = :t WHERE id = :id");
        return $st->execute([':t' => $hash, ':id' => $userId]);
    }

    // ====== تذكرني (أسماء alias متوافقة مع Auth لديك) ======
    // Auth::attempt() يستدعي updateRememberHash(...)
    public static function updateRememberHash(int $userId, ?string $hash): bool
    {
        return self::updateRememberTokenHash($userId, $hash);
    }

    // Auth::initRememberedLogin() قد يستدعي findByRememberHash(...)
    public static function findByRememberHash(string $hash): ?array
    {
        return self::findByRememberTokenHash($hash);
    }

    // ====== CRUD أساسية ======
    public static function emailExists(string $email, ?int $ignoreId = null): bool
    {
        if ($ignoreId) {
            $st = DB::pdo()->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
            $st->execute([$email, $ignoreId]);
        } else {
            $st = DB::pdo()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $st->execute([$email]);
        }
        return (bool)$st->fetchColumn();
    }

    public static function create(array $v): int
    {
        $sql = "INSERT INTO users (name, email, mobile, password, role, market_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $st = DB::pdo()->prepare($sql);
        $st->execute([
            $v['name'],
            $v['email'],
            $v['mobile'],
            password_hash($v['password'], PASSWORD_BCRYPT),
            $v['role'],
            ($v['market_id'] !== '' ? (int)$v['market_id'] : null),
            $v['status'],
        ]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function updateById(int $id, array $v): bool
    {
        $set  = "name=?, email=?, mobile=?, role=?, market_id=?, status=?";
        $args = [
            $v['name'],
            $v['email'],
            $v['mobile'],
            $v['role'],
            ($v['market_id'] !== '' ? (int)$v['market_id'] : null),
            $v['status'],
        ];

        if (!empty($v['password'])) {
            $set .= ", password=?";
            $args[] = password_hash($v['password'], PASSWORD_BCRYPT);
        }

        $args[] = $id;
        $st = DB::pdo()->prepare("UPDATE users SET {$set} WHERE id=?");
        return $st->execute($args);
    }

    public static function deleteById(int $id): bool
    {
        $st = DB::pdo()->prepare("DELETE FROM users WHERE id=?");
        return $st->execute([$id]);
    }

    public static function filter(array $f): array
    {
        $sql = "SELECT u.id,u.name,u.email,u.mobile,u.role,u.status,u.market_id,u.created_at,
                   m.name AS market_name
            FROM users u
            LEFT JOIN markets m ON m.id = u.market_id
            WHERE 1=1";
        $args = [];

        // بحث نصي على الاسم/البريد/الجوال
        if ($f['q'] !== '') {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
            $q = '%' . $f['q'] . '%';
            array_push($args, $q, $q, $q);
        }
        // الدور
        if ($f['role'] !== '' && in_array($f['role'], ['admin', 'owner', 'user'], true)) {
            $sql .= " AND u.role = ?";
            $args[] = $f['role'];
        }
        // الحالة
        if ($f['status'] !== '' && in_array($f['status'], ['active', 'inactive', 'blocked', 'removed'], true)) {
            $sql .= " AND u.status = ?";
            $args[] = $f['status'];
        }
        // المتجر
        if ($f['market_id'] !== '' && ctype_digit($f['market_id'])) {
            $sql .= " AND u.market_id = ?";
            $args[] = (int)$f['market_id'];
        }

        $sql .= " ORDER BY u.id DESC";
        $st = DB::pdo()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
