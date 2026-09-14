<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. MySQL/MariaDB is the production target; the sqlite driver
 * exists only so the integration test-suite can run without a database server.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private string $driver;
    private int $txDepth = 0;
    private int $queryCount = 0;

    private function __construct(array $cfg)
    {
        $this->driver = (string)($cfg['driver'] ?? 'mysql');

        if ($this->driver === 'sqlite') {
            $dsn = 'sqlite:' . ($cfg['database'] ?? ':memory:');
            $user = null;
            $pass = null;
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1',
                $cfg['port'] ?? '3306',
                $cfg['database'] ?? '',
                $cfg['charset'] ?? 'utf8mb4'
            );
            $user = $cfg['username'] ?? null;
            $pass = $cfg['password'] ?? null;
        }

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            Logger::error('Database connection failed', ['message' => $e->getMessage()]);
            throw new RuntimeException('DB_CONNECTION_FAILED: ' . $e->getMessage(), 0, $e);
        }

        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self((array)Config::get('database.connection', []));
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $this->queryCount++;
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with((string)$k, ':') ? $k : ':' . $k);
            $type = match (true) {
                is_int($v)  => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                is_null($v) => PDO::PARAM_NULL,
                default     => PDO::PARAM_STR,
            };
            $stmt->bindValue($key, $v, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdent($table),
            implode(', ', array_map([$this, 'quoteIdent'], $cols)),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        $this->run($sql, $data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = $this->quoteIdent($c) . ' = :set_' . $c;
        }
        $params = [];
        foreach ($data as $k => $v) {
            $params['set_' . $k] = $v;
        }
        foreach ($whereParams as $k => $v) {
            $params[is_int($k) ? $k : ltrim((string)$k, ':')] = $v;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->quoteIdent($table), implode(', ', $sets), $where);
        return $this->execute($sql, $params);
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->execute(sprintf('DELETE FROM %s WHERE %s', $this->quoteIdent($table), $where), $params);
    }

    /** Nested-safe transaction helper. */
    public function transaction(callable $fn): mixed
    {
        $this->begin();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function begin(): void
    {
        if ($this->txDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT sp' . $this->txDepth);
        }
        $this->txDepth++;
    }

    public function commit(): void
    {
        $this->txDepth--;
        if ($this->txDepth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT sp' . $this->txDepth);
        }
    }

    public function rollBack(): void
    {
        $this->txDepth--;
        if ($this->txDepth <= 0) {
            $this->txDepth = 0;
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } else {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT sp' . $this->txDepth);
        }
    }

    public function quoteIdent(string $ident): string
    {
        $ident = preg_replace('/[^A-Za-z0-9_.]/', '', $ident) ?? '';
        if (str_contains($ident, '.')) {
            return implode('.', array_map(fn ($p) => $this->quoteIdent($p), explode('.', $ident)));
        }
        return $this->driver === 'mysql' ? '`' . $ident . '`' : '"' . $ident . '"';
    }

    /** Row-level lock clause (no-op on sqlite). */
    public function forUpdate(): string
    {
        return $this->driver === 'mysql' ? ' FOR UPDATE' : '';
    }
}
