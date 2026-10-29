<?php
declare(strict_types=1);

namespace App\Core;

/** Lightweight activity trail used by the audit page and approval histories. */
final class Audit
{
    public static function log(string $action, string $entity = '', ?int $entityId = null, array $meta = []): void
    {
        try {
            Database::insert('activity_logs', [
                'employee_id' => Auth::id(),
                'action'      => $action,
                'entity'      => $entity,
                'entity_id'   => $entityId,
                'meta'        => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // never break a request because of logging
        }
    }

    public static function recent(int $limit = 20): array
    {
        return Database::all(
            'SELECT l.*, e.full_name FROM activity_logs l
             LEFT JOIN employees e ON e.id = l.employee_id
             ORDER BY l.id DESC LIMIT ' . max(1, $limit)
        );
    }
}
