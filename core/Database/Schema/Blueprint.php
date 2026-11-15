<?php

declare(strict_types=1);

namespace Agus\Database\Schema;

use Agus\Database\QueryBuilder;

/**
 * Describes a table in PHP, then compiles it to MySQL or SQLite SQL.
 *
 *   Schema::create('listings', function (Blueprint $t) {
 *       $t->id();
 *       $t->foreignId('shop_id')->references('shops')->onDelete('cascade');
 *       $t->string('name', 80);
 *       $t->decimal('price', 8, 2);
 *       $t->integer('quantity')->default(0);
 *       $t->timestamps();
 *   });
 */
class Blueprint
{
    /** @var Column[] */
    private array $columns = [];
    private array $indexes = [];

    public function __construct(private string $table)
    {
        QueryBuilder::assertIdentifier($table);
    }

    private function add(string $name, string $type, array $options = []): Column
    {
        QueryBuilder::assertIdentifier($name);
        return $this->columns[] = new Column($name, $type, $options);
    }

    public function id(string $name = 'id'): Column
    {
        return $this->add($name, 'id');
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->add($name, 'string', ['length' => $length]);
    }

    public function text(string $name): Column
    {
        return $this->add($name, 'text');
    }

    public function integer(string $name): Column
    {
        return $this->add($name, 'integer');
    }

    public function bigInteger(string $name): Column
    {
        return $this->add($name, 'bigInteger');
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        return $this->add($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function boolean(string $name): Column
    {
        return $this->add($name, 'boolean');
    }

    public function date(string $name): Column
    {
        return $this->add($name, 'date');
    }

    public function dateTime(string $name): Column
    {
        return $this->add($name, 'dateTime');
    }

    /** A column restricted to a few values: ->enum('status', ['pending', 'paid']) */
    public function enum(string $name, array $values): Column
    {
        return $this->add($name, 'enum', ['values' => $values]);
    }

    /** An unsigned BIGINT for pointing at another table's id. Chain ->references('table'). */
    public function foreignId(string $name): Column
    {
        return $this->add($name, 'foreignId');
    }

    public function timestamps(): void
    {
        $this->dateTime('created_at')->nullable();
        $this->dateTime('updated_at')->nullable();
    }

    public function index(string|array $columns): void
    {
        $this->indexes[] = (array) $columns;
    }

    /** @return string[] SQL statements (CREATE TABLE plus any CREATE INDEX). */
    public function toSql(string $driver): array
    {
        $lines = [];
        $foreign = [];

        foreach ($this->columns as $column) {
            $lines[] = '  ' . $column->toSql($driver);
            if ($column->option('references')) {
                $foreign[] = sprintf(
                    '  FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s',
                    $column->name,
                    $column->option('references'),
                    $column->option('referencesColumn', 'id'),
                    strtoupper((string) $column->option('onDelete', 'restrict'))
                );
            }
            if ($column->option('unique')) {
                // Table constraints must come after all column definitions (SQLite requires it).
                $foreign[] = $driver === 'sqlite'
                    ? "  UNIQUE ({$column->name})"
                    : "  UNIQUE KEY {$this->table}_{$column->name}_unique ({$column->name})";
            }
        }

        $body = implode(",\n", array_merge($lines, $foreign));
        $sql = ["CREATE TABLE {$this->table} (\n$body\n)" . ($driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '')];

        foreach ($this->indexes as $cols) {
            foreach ($cols as $c) {
                QueryBuilder::assertIdentifier($c);
            }
            $sql[] = sprintf('CREATE INDEX %s_%s_index ON %s (%s)', $this->table, implode('_', $cols), $this->table, implode(', ', $cols));
        }
        return $sql;
    }
}
