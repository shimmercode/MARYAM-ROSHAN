<?php
declare(strict_types=1);

namespace App\Repositories;

final class ServiceRepository extends BaseRepository
{
    protected string $table = 'services';
    protected bool $softDeletes = true;
    protected array $sortable = ['id', 'name', 'price', 'duration_minutes', 'created_at'];
    protected array $columns = [
        'id', 'category_id', 'name', 'slug', 'description', 'short_description', 'duration_minutes',
        'buffer_minutes', 'price', 'cost', 'commission_type', 'commission_value', 'loyalty_points',
        'image', 'is_featured', 'requires_resource', 'status', 'meta_title', 'meta_description',
    ];

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['s.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = '(s.name LIKE :s1 OR s.short_description LIKE :s2)';
            $params += ['s1' => $like, 's2' => $like];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 's.category_id = :cat';
            $params['cat'] = (int)$filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = :st';
            $params['st'] = $filters['status'];
        }
        if (!empty($filters['branch_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM service_branches sb WHERE sb.service_id = s.id AND sb.branch_id = :bid)';
            $params['bid'] = (int)$filters['branch_id'];
        }
        $w    = implode(' AND ', $where);
        $map  = ['name' => 's.name', 'price' => 's.price', 'duration_minutes' => 's.duration_minutes', 'created_at' => 's.created_at', 'id' => 's.id'];
        $col  = $map[$filters['sort'] ?? ''] ?? 's.id';
        $dir  = strtoupper((string)($filters['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT s.id, s.name, s.slug, s.price, s.cost, s.duration_minutes, s.status, s.image,
                       s.is_featured, s.online_booking, s.commission_type, s.commission_value,
                       c.name AS category_name, c.icon AS category_icon,
                       (SELECT COUNT(*) FROM staff_services ss WHERE ss.service_id = s.id) AS staff_count
                FROM services s
                JOIN service_categories c ON c.id = s.category_id
                WHERE {$w} ORDER BY {$col} {$dir}";
        $countSql = "SELECT COUNT(*) FROM services s WHERE {$w}";
        return $this->paginateQuery($sql, $countSql, $params, $page, $perPage);
    }

    public function findFull(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT s.*, c.name AS category_name, c.slug AS category_slug
             FROM services s JOIN service_categories c ON c.id = s.category_id
             WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT s.*, c.name AS category_name, c.slug AS category_slug
             FROM services s JOIN service_categories c ON c.id = s.category_id
             WHERE s.slug = :slug AND s.status = 'ACTIVE' AND s.deleted_at IS NULL LIMIT 1",
            ['slug' => $slug]
        );
    }

    public function categories(bool $activeOnly = true): array
    {
        $where = $activeOnly ? "WHERE status = 'ACTIVE'" : '';
        return $this->db->select(
            "SELECT id, name, slug, icon, description, sort_order, status,
                    (SELECT COUNT(*) FROM services s WHERE s.category_id = service_categories.id AND s.deleted_at IS NULL) AS services_count
             FROM service_categories {$where} ORDER BY sort_order, name"
        );
    }

    public function activeList(?int $branchId = null): array
    {
        $sql = "SELECT s.id, s.name, s.price, s.duration_minutes, s.buffer_minutes, s.category_id,
                       c.name AS category_name
                FROM services s JOIN service_categories c ON c.id = s.category_id
                WHERE s.status = 'ACTIVE' AND s.deleted_at IS NULL";
        $params = [];
        if ($branchId) {
            $sql .= ' AND (NOT EXISTS (SELECT 1 FROM service_branches sb WHERE sb.service_id = s.id)
                           OR EXISTS (SELECT 1 FROM service_branches sb2 WHERE sb2.service_id = s.id AND sb2.branch_id = :b))';
            $params['b'] = $branchId;
        }
        return $this->db->select($sql . ' ORDER BY c.sort_order, s.name', $params);
    }

    public function publicList(?string $categorySlug = null, int $limit = 100): array
    {
        $sql = "SELECT s.id, s.name, s.slug, s.short_description, s.price, s.duration_minutes, s.image,
                       s.is_featured, c.name AS category_name, c.slug AS category_slug
                FROM services s JOIN service_categories c ON c.id = s.category_id
                WHERE s.status = 'ACTIVE' AND s.deleted_at IS NULL";
        $params = [];
        if ($categorySlug) {
            $sql .= ' AND c.slug = :cs';
            $params['cs'] = $categorySlug;
        }
        return $this->db->select($sql . " ORDER BY s.is_featured DESC, c.sort_order, s.name LIMIT {$limit}", $params);
    }

    public function staffFor(int $serviceId, ?int $branchId = null): array
    {
        $sql = "SELECT s.id, s.first_name, s.last_name, s.avatar, s.rating, s.branch_id, s.color,
                       ss.custom_price, ss.custom_duration
                FROM staff s
                JOIN staff_services ss ON ss.staff_id = s.id
                WHERE ss.service_id = :sid AND s.status = 'ACTIVE' AND s.deleted_at IS NULL";
        $params = ['sid' => $serviceId];
        if ($branchId) {
            $sql .= ' AND s.branch_id = :bid';
            $params['bid'] = $branchId;
        }
        return $this->db->select($sql . ' ORDER BY s.rating DESC, s.first_name', $params);
    }

    public function syncStaff(int $serviceId, array $staffIds): void
    {
        $this->db->transaction(function () use ($serviceId, $staffIds): void {
            $this->db->delete('staff_services', 'service_id = :s', ['s' => $serviceId]);
            foreach (array_unique(array_map('intval', $staffIds)) as $sid) {
                $this->db->insert('staff_services', ['staff_id' => $sid, 'service_id' => $serviceId]);
            }
        });
    }

    public function syncBranches(int $serviceId, array $branchIds): void
    {
        $this->db->transaction(function () use ($serviceId, $branchIds): void {
            $this->db->delete('service_branches', 'service_id = :s', ['s' => $serviceId]);
            foreach (array_unique(array_map('intval', $branchIds)) as $bid) {
                $this->db->insert('service_branches', ['service_id' => $serviceId, 'branch_id' => $bid]);
            }
        });
    }

    public function branchIds(int $serviceId): array
    {
        return array_map('intval', array_column(
            $this->db->select('SELECT branch_id FROM service_branches WHERE service_id = :s', ['s' => $serviceId]),
            'branch_id'
        ));
    }

    public function staffIds(int $serviceId): array
    {
        return array_map('intval', array_column(
            $this->db->select('SELECT staff_id FROM staff_services WHERE service_id = :s', ['s' => $serviceId]),
            'staff_id'
        ));
    }

    public function recipe(int $serviceId): array
    {
        $recipe = $this->db->selectOne('SELECT id, name, status FROM service_recipes WHERE service_id = :s', ['s' => $serviceId]);
        if ($recipe === null) {
            return ['recipe' => null, 'items' => []];
        }
        $items = $this->db->select(
            'SELECT ri.id, ri.product_id, ri.quantity, ri.unit, p.name AS product_name, p.sku, p.purchase_price
             FROM recipe_items ri JOIN products p ON p.id = ri.product_id
             WHERE ri.recipe_id = :r',
            ['r' => (int)$recipe['id']]
        );
        return ['recipe' => $recipe, 'items' => $items];
    }

    public function priceFor(int $serviceId, ?int $staffId = null): float
    {
        if ($staffId) {
            $custom = $this->db->scalar(
                'SELECT custom_price FROM staff_services WHERE staff_id = :st AND service_id = :sv',
                ['st' => $staffId, 'sv' => $serviceId]
            );
            if ($custom !== null && $custom !== '') {
                return (float)$custom;
            }
        }
        return (float)$this->db->scalar('SELECT price FROM services WHERE id = :s', ['s' => $serviceId]);
    }

    public function durationFor(int $serviceId, ?int $staffId = null): int
    {
        if ($staffId) {
            $custom = $this->db->scalar(
                'SELECT custom_duration FROM staff_services WHERE staff_id = :st AND service_id = :sv',
                ['st' => $staffId, 'sv' => $serviceId]
            );
            if ($custom !== null && $custom !== '') {
                return (int)$custom;
            }
        }
        return (int)$this->db->scalar('SELECT duration_minutes FROM services WHERE id = :s', ['s' => $serviceId]);
    }
}
