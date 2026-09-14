<?php
declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected bool $softDeletes = true;
    protected array $sortable = ['id', 'first_name', 'last_name', 'mobile', 'status', 'last_login_at', 'created_at'];
    protected array $columns = [
        'id', 'first_name', 'last_name', 'mobile', 'email', 'avatar', 'status', 'branch_id',
        'last_login_at', 'last_login_ip', 'two_factor_enabled', 'must_change_password', 'created_at',
    ];

    public function findByMobile(string $mobile): ?array
    {
        return $this->db->selectOne(
            'SELECT id, first_name, last_name, mobile, email, password_hash, status, branch_id, avatar,
                    two_factor_enabled, must_change_password
             FROM users WHERE mobile = :m AND deleted_at IS NULL LIMIT 1',
            ['m' => $mobile]
        );
    }

    public function findByIdentifier(string $identifier): ?array
    {
        return $this->db->selectOne(
            'SELECT id, first_name, last_name, mobile, email, password_hash, status, branch_id, avatar,
                    two_factor_enabled, must_change_password
             FROM users WHERE (mobile = :i OR email = :i2) AND deleted_at IS NULL LIMIT 1',
            ['i' => $identifier, 'i2' => $identifier]
        );
    }

    public function rolesOf(int $userId): array
    {
        return $this->db->select(
            'SELECT r.id, r.slug, r.name FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :u',
            ['u' => $userId]
        );
    }

    public function permissionsOf(int $userId): array
    {
        $rows = $this->db->select(
            'SELECT DISTINCT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :u',
            ['u' => $userId]
        );
        return array_column($rows, 'slug');
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->db->transaction(function () use ($userId, $roleIds): void {
            $this->db->delete('user_roles', 'user_id = :u', ['u' => $userId]);
            foreach (array_unique(array_map('intval', $roleIds)) as $rid) {
                $this->db->insert('user_roles', ['user_id' => $userId, 'role_id' => $rid]);
            }
        });
    }

    public function assignRoleBySlug(int $userId, string $slug): void
    {
        $roleId = $this->db->scalar('SELECT id FROM roles WHERE slug = :s', ['s' => $slug]);
        if ($roleId) {
            $exists = $this->db->scalar(
                'SELECT COUNT(*) FROM user_roles WHERE user_id = :u AND role_id = :r',
                ['u' => $userId, 'r' => (int)$roleId]
            );
            if (!$exists) {
                $this->db->insert('user_roles', ['user_id' => $userId, 'role_id' => (int)$roleId]);
            }
        }
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['u.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = '(u.first_name LIKE :s OR u.last_name LIKE :s2 OR u.mobile LIKE :s3)';
            $like = '%' . $filters['search'] . '%';
            $params += ['s' => $like, 's2' => $like, 's3' => $like];
        }
        if (!empty($filters['status'])) {
            $where[] = 'u.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['role'])) {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.slug = :role)';
            $params['role'] = $filters['role'];
        }
        $w    = implode(' AND ', $where);
        $sort = $this->safeSort($filters['sort'] ?? null, 'u.id', $filters['dir'] ?? 'DESC');
        $sort = str_replace(['first_name', 'last_name', 'mobile', 'status', 'last_login_at', 'created_at', 'id'], [
            'u.first_name', 'u.last_name', 'u.mobile', 'u.status', 'u.last_login_at', 'u.created_at', 'u.id',
        ], $sort);

        $sql = "SELECT u.id, u.first_name, u.last_name, u.mobile, u.email, u.status, u.avatar,
                       u.last_login_at, u.created_at, b.name AS branch_name
                FROM users u
                LEFT JOIN branches b ON b.id = u.branch_id
                WHERE {$w} ORDER BY {$sort}";
        $countSql = "SELECT COUNT(*) FROM users u WHERE {$w}";
        $result = $this->paginateQuery($sql, $countSql, $params, $page, $perPage);

        foreach ($result['data'] as &$row) {
            $row['roles'] = array_column($this->rolesOf((int)$row['id']), 'name');
        }
        return $result;
    }

    public function updateLastLogin(int $userId, string $ip): void
    {
        $this->update($userId, ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip]);
    }
}
