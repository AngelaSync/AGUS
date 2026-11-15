<?php

declare(strict_types=1);

namespace Agus\Database\Schema;

use Agus\Database\Database;
use Agus\Database\QueryBuilder;

/** Entry point used inside migration files. */
final class Schema
{
    public static function create(string $table, callable $define): void
    {
        $blueprint = new Blueprint($table);
        $define($blueprint);
        foreach ($blueprint->toSql(Database::driver()) as $sql) {
            Database::connection()->exec($sql);
        }
    }

    public static function dropIfExists(string $table): void
    {
        QueryBuilder::assertIdentifier($table);
        Database::connection()->exec("DROP TABLE IF EXISTS $table");
    }

    public static function hasTable(string $table): bool
    {
        QueryBuilder::assertIdentifier($table);
        $sql = Database::driver() === 'sqlite'
            ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?"
            : 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?';
        return Database::selectOne($sql, [$table]) !== null;
    }

    /** Shows the SQL a Blueprint would run, without running it (handy for documentation). */
    public static function toSql(string $table, callable $define, string $driver = 'mysql'): array
    {
        $blueprint = new Blueprint($table);
        $define($blueprint);
        return $blueprint->toSql($driver);
    }
}
