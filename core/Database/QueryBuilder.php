<?php

declare(strict_types=1);

namespace Agus\Database;

/**
 * Builds SQL step by step so you rarely write it by hand.
 *
 *   Database::table('listings')
 *       ->where('shop_id', 3)
 *       ->where('price', '<', 50)
 *       ->whereNull('deleted_at')
 *       ->orderBy('created_at', 'desc')
 *       ->limit(10)
 *       ->get();
 *
 * Every value becomes a "?" placeholder; only column names are written into the SQL,
 * and those are checked against a safe pattern.
 */
class QueryBuilder
{
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private ?string $modelClass = null;

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public function __construct(private string $table)
    {
        self::assertIdentifier($table);
    }

    /** Return rows as Model objects instead of arrays. */
    public function asModel(string $class): self
    {
        $this->modelClass = $class;
        return $this;
    }

    public function select(string ...$columns): self
    {
        foreach ($columns as $column) {
            self::assertIdentifier($column, allowAlias: true);
        }
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    /**
     * where('email', $email)            -> email = ?
     * where('price', '<', 50)            -> price < ?
     */
    public function where(string $column, mixed $operator, mixed $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            [$operator, $value] = ['=', $operator];
        }
        $operator = strtoupper((string) $operator);
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Operator [$operator] is not allowed.");
        }
        self::assertIdentifier($column);
        $this->wheres[] = [$boolean, "$column $operator ?"];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        return func_num_args() === 2
            ? $this->where($column, '=', $operator, 'OR')
            : $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values): self
    {
        self::assertIdentifier($column);
        if (!$values) {
            $this->wheres[] = ['AND', '1 = 0'];
            return $this;
        }
        $this->wheres[] = ['AND', "$column IN (" . implode(', ', array_fill(0, count($values), '?')) . ')'];
        array_push($this->bindings, ...array_values($values));
        return $this;
    }

    public function whereNull(string $column): self
    {
        self::assertIdentifier($column);
        $this->wheres[] = ['AND', "$column IS NULL"];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        self::assertIdentifier($column);
        $this->wheres[] = ['AND', "$column IS NOT NULL"];
        return $this;
    }

    /** Search several columns at once: ->search(['name', 'description'], 'pandesal') */
    public function search(array $columns, string $term): self
    {
        if (trim($term) === '' || !$columns) {
            return $this;
        }
        $parts = [];
        foreach ($columns as $column) {
            self::assertIdentifier($column);
            $parts[] = "$column LIKE ?";
            $this->bindings[] = '%' . trim($term) . '%';
        }
        $this->wheres[] = ['AND', '(' . implode(' OR ', $parts) . ')'];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        self::assertIdentifier($table, allowAlias: true);
        self::assertIdentifier($first);
        self::assertIdentifier($second);
        if (!in_array($operator, ['=', '!=', '<', '>', '<=', '>='], true)) {
            throw new \InvalidArgumentException("Join operator [$operator] is not allowed.");
        }
        $type = in_array(strtoupper($type), ['INNER', 'LEFT', 'RIGHT'], true) ? strtoupper($type) : 'INNER';
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        self::assertIdentifier($column);
        $this->orders[] = $column . ' ' . (strtolower($direction) === 'desc' ? 'DESC' : 'ASC');
        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    // ---- reading ------------------------------------------------------------

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;
        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->compileWheres();
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ($this->limit === null ? ' LIMIT ' . PHP_INT_MAX : '') . ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        $rows = Database::select($this->toSql(), $this->bindings);
        return $this->modelClass ? array_map(fn (array $row) => $this->modelClass::fromRow($row), $rows) : $rows;
    }

    public function first(): mixed
    {
        $clone = clone $this;
        $rows = $clone->limit(1)->get();
        return $rows[0] ?? null;
    }

    public function find(int|string $id, string $key = 'id'): mixed
    {
        return (clone $this)->where($key, $id)->first();
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) AS aggregate FROM ' . $this->table
            . ($this->joins ? ' ' . implode(' ', $this->joins) : '')
            . $this->compileWheres();
        return (int) (Database::selectOne($sql, $this->bindings)['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): float
    {
        self::assertIdentifier($column);
        $sql = 'SELECT COALESCE(SUM(' . $column . '), 0) AS aggregate FROM ' . $this->table
            . ($this->joins ? ' ' . implode(' ', $this->joins) : '')
            . $this->compileWheres();
        return (float) (Database::selectOne($sql, $this->bindings)['aggregate'] ?? 0);
    }

    /**
     * Splits results into pages. Reads ?page= from the URL by default.
     * Returns ['data', 'total', 'per_page', 'current_page', 'last_page'].
     */
    public function paginate(int $perPage = 10, ?int $page = null): array
    {
        $page = max(1, $page ?? (int) ($_GET['page'] ?? 1));
        $total = (clone $this)->count();
        $data = (clone $this)->limit($perPage)->offset(($page - 1) * $perPage)->get();
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
        ];
    }

    // ---- writing ------------------------------------------------------------

    /** Inserts one row and returns its new id. */
    public function insert(array $data): int
    {
        if (!$data) {
            throw new \InvalidArgumentException('Nothing to insert.');
        }
        foreach (array_keys($data) as $column) {
            self::assertIdentifier((string) $column);
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', array_keys($data)),
            implode(', ', array_fill(0, count($data), '?'))
        );
        Database::query($sql, array_values($data));
        return Database::lastInsertId();
    }

    /** Updates matching rows and returns how many changed. Refuses to run without a where(). */
    public function update(array $data): int
    {
        if (!$this->wheres) {
            throw new \LogicException('update() without where() would change every row. Add a where().');
        }
        $sets = [];
        foreach (array_keys($data) as $column) {
            self::assertIdentifier((string) $column);
            $sets[] = "$column = ?";
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . $this->compileWheres();
        return Database::statement($sql, array_merge(array_values($data), $this->bindings));
    }

    /** Adds to a number column: ->where('id', 5)->increment('stock', -1) */
    public function increment(string $column, int|float $by = 1): int
    {
        self::assertIdentifier($column);
        if (!$this->wheres) {
            throw new \LogicException('increment() needs a where().');
        }
        $sql = "UPDATE {$this->table} SET $column = $column + ?" . $this->compileWheres();
        return Database::statement($sql, array_merge([$by], $this->bindings));
    }

    public function delete(): int
    {
        if (!$this->wheres) {
            throw new \LogicException('delete() without where() would remove every row. Add a where().');
        }
        return Database::statement('DELETE FROM ' . $this->table . $this->compileWheres(), $this->bindings);
    }

    // ---- internals ----------------------------------------------------------

    private function compileWheres(): string
    {
        if (!$this->wheres) {
            return '';
        }
        $sql = '';
        foreach ($this->wheres as $i => [$boolean, $clause]) {
            $sql .= ($i === 0 ? ' WHERE ' : " $boolean ") . $clause;
        }
        return $sql;
    }

    /** Only letters, digits, underscores and dots (table.column) may become SQL identifiers. */
    public static function assertIdentifier(string $name, bool $allowAlias = false): void
    {
        $pattern = $allowAlias
            ? '/^(\*|[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_*][A-Za-z0-9_]*)?)( (AS )?[A-Za-z_][A-Za-z0-9_]*)?$/i'
            : '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';
        if (!preg_match($pattern, $name)) {
            throw new \InvalidArgumentException("Invalid SQL identifier [$name].");
        }
    }
}
