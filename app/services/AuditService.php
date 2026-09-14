<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;

final class AuditService
{
    private const SENSITIVE = ['password', 'password_hash', 'remember_token', 'token_hash', 'code_hash', 'api_key', 'secret'];

    public static function log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?int $userId = null
    ): void {
        try {
            $req = Request::capture();
            Database::instance()->insert('audit_logs', [
                'user_id'    => $userId ?? AuthService::id(),
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'old_data'   => $oldData !== null ? json_encode(self::scrub($oldData), JSON_UNESCAPED_UNICODE) : null,
                'new_data'   => $newData !== null ? json_encode(self::scrub($newData), JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $req->ip(),
                'user_agent' => $req->userAgent(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the request.
            Logger::error('Audit log failed', ['action' => $action, 'message' => $e->getMessage()]);
        }
    }

    private static function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string)$k), self::SENSITIVE, true)) {
                $data[$k] = '***';
            } elseif (is_array($v)) {
                $data[$k] = self::scrub($v);
            }
        }
        return $data;
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $db     = Database::instance();
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['action'])) {
            $where[] = 'a.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['entity'])) {
            $where[] = 'a.entity = :entity';
            $params['entity'] = $filters['entity'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :uid';
            $params['uid'] = (int)$filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= :df';
            $params['df'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= :dt';
            $params['dt'] = $filters['date_to'] . ' 23:59:59';
        }
        $w      = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);
        $total  = (int)$db->scalar("SELECT COUNT(*) FROM audit_logs a WHERE {$w}", $params);
        $rows   = $db->select(
            "SELECT a.id, a.action, a.entity, a.entity_id, a.ip_address, a.created_at,
                    a.old_data, a.new_data,
                    CONCAT(u.first_name, ' ', u.last_name) AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$w} ORDER BY a.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }
}
