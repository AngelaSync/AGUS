<?php

declare(strict_types=1);

namespace Agus\Database\Schema;

/** One column inside a Blueprint. Modifiers can be chained: ->nullable()->default(0) */
class Column
{
    public function __construct(public string $name, public string $type, private array $options = [])
    {
    }

    public function nullable(bool $value = true): self
    {
        $this->options['nullable'] = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->options['default'] = $value;
        $this->options['hasDefault'] = true;
        return $this;
    }

    public function unique(): self
    {
        $this->options['unique'] = true;
        return $this;
    }

    public function references(string $table, string $column = 'id'): self
    {
        $this->options['references'] = $table;
        $this->options['referencesColumn'] = $column;
        return $this;
    }

    /** cascade | restrict | set null */
    public function onDelete(string $action): self
    {
        $allowed = ['cascade', 'restrict', 'set null', 'no action'];
        $this->options['onDelete'] = in_array(strtolower($action), $allowed, true) ? strtolower($action) : 'restrict';
        return $this;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function toSql(string $driver): string
    {
        $sqlite = $driver === 'sqlite';

        if ($this->type === 'id') {
            return $sqlite
                ? "{$this->name} INTEGER PRIMARY KEY AUTOINCREMENT"
                : "{$this->name} BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        }

        $type = match ($this->type) {
            'string' => $sqlite ? 'TEXT' : 'VARCHAR(' . (int) $this->option('length', 255) . ')',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'bigInteger' => 'BIGINT',
            'foreignId' => $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED',
            'decimal' => sprintf('DECIMAL(%d,%d)', $this->option('precision', 10), $this->option('scale', 2)),
            'boolean' => $sqlite ? 'INTEGER' : 'TINYINT(1)',
            'date' => $sqlite ? 'TEXT' : 'DATE',
            'dateTime' => $sqlite ? 'TEXT' : 'DATETIME',
            'enum' => $sqlite
                ? 'TEXT CHECK (' . $this->name . ' IN (' . $this->quotedValues() . '))'
                : 'ENUM(' . $this->quotedValues() . ')',
            default => throw new \InvalidArgumentException("Unknown column type [{$this->type}]."),
        };

        $sql = "{$this->name} $type";
        $sql .= $this->option('nullable') ? ' NULL' : ' NOT NULL';
        if ($this->option('hasDefault')) {
            $sql .= ' DEFAULT ' . $this->literal($this->options['default']);
        }
        return $sql;
    }

    private function quotedValues(): string
    {
        return implode(', ', array_map(fn ($v) => $this->literal((string) $v), (array) $this->option('values', [])));
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => "'" . str_replace("'", "''", (string) $value) . "'",
        };
    }
}
