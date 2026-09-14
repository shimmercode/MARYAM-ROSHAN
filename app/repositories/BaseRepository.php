<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

abstract class BaseRepository
{
    protected Database $db;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $softDeletes = false;
    /** Columns allowed in ORDER BY (whitelist against SQL injection). */
    protected array $sortable = ['id'];
    /** Columns selected by default — never SELECT *. */
    protected array $columns = ['*'];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function db(): Database
    {
        return $this->db;
    }

    protected function selectList(): string
    {
        return implode(', ', $this->columns);
    }

    public function find(int $id): ?array
    {
        $sql = sprintf('SELECT %s FROM %s WHERE %s = :id', $this->selectList(), $this->table, $this->primaryKey);
        if ($this->softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return $this->db->selectOne($sql . ' LIMIT 1', ['id' => $id]);
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $col = $this->db->quoteIdent($column);
        $sql = sprintf('SELECT %s FROM %s WHERE %s = :v', $this->selectList(), $this->table, $col);
        if ($this->softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return $this->db->selectOne($sql . ' LIMIT 1', ['v' => $value]);
    }

    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update($this->table, $data, $this->primaryKey . ' = :pk', ['pk' => $id]);
    }

    public function delete(int $id): int
    {
        if ($this->softDeletes) {
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        return $this->db->delete($this->table, $this->primaryKey . ' = :pk', ['pk' => $id]);
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        if ($this->softDeletes) {
            $where .= ' AND deleted_at IS NULL';
        }
        return (int)$this->db->scalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $params);
    }

    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Generic paginated listing.
     * @return array{data: array, total: int, page: int, per_page: int, last_page: int}
     */
    protected function paginateQuery(string $sql, string $countSql, array $params, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;
        $total   = (int)$this->db->scalar($countSql, $params);
        $rows    = $this->db->select($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'from'      => $total ? $offset + 1 : 0,
            'to'        => min($offset + $perPage, $total),
        ];
    }

    protected function safeSort(?string $column, string $default, ?string $direction = 'DESC'): string
    {
        $col = in_array((string)$column, $this->sortable, true) ? (string)$column : $default;
        $dir = strtoupper((string)$direction) === 'ASC' ? 'ASC' : 'DESC';
        return $col . ' ' . $dir;
    }
}
