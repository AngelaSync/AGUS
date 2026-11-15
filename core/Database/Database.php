<?php

declare(strict_types=1);

namespace Agus\Database;

use PDO;
use PDOStatement;

/**
 * One shared PDO connection (MySQL/MariaDB or SQLite), opened the first
 * time it is needed. Every query uses prepared statements, so values are
 * never pasted into SQL (protects against SQL injection).
 *
 *   Database::query('SELECT * FROM users WHERE email = ?', [$email])->fetch();
 *   Database::table('users')->where('id', 5)->first();
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static array $queryLog = [];

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect((array) config('database'));
        }
        return self::$pdo;
    }

    /** Swap the connection (used by tests with an in-memory SQLite database). */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function connect(array $config): PDO
    {
        $driver = $config['driver'] ?? 'mysql';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $path = (string) ($config['sqlite']['database'] ?? ':memory:');
            if ($path !== ':memory:' && !is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }

        $c = $config['mysql'] ?? [];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'] ?? '127.0.0.1',
            $c['port'] ?? '3306',
            $c['database'] ?? '',
            $c['charset'] ?? 'utf8mb4'
        );

        try {
            return new PDO($dsn, (string) ($c['username'] ?? 'root'), (string) ($c['password'] ?? ''), $options);
        } catch (\PDOException $e) {
            // Friendlier message for the most common XAMPP mistakes.
            throw new \RuntimeException(
                'Could not connect to MySQL at ' . ($c['host'] ?? '127.0.0.1') . ' (database "' . ($c['database'] ?? '') . '"). '
                . 'Is MySQL running in XAMPP, and does the database exist? Check DB_* in .env. Details: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public static function driver(): string
    {
        return self::connection()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function query(string $sql, array $bindings = []): PDOStatement
    {
        $start = microtime(true);
        $statement = self::connection()->prepare($sql);
        $positional = array_is_list($bindings);
        foreach ($bindings as $key => $value) {
            // [?, ?] placeholders use 1-based positions; [':email' => ...] uses names.
            $param = $positional ? $key + 1 : ':' . ltrim((string) $key, ':');
            $statement->bindValue($param, $value, self::pdoType($value));
        }
        $statement->execute();

        if (config('app.debug')) {
            self::$queryLog[] = ['sql' => $sql, 'bindings' => $bindings, 'ms' => round((microtime(true) - $start) * 1000, 2)];
        }
        return $statement;
    }

    private static function pdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    public static function select(string $sql, array $bindings = []): array
    {
        return self::query($sql, $bindings)->fetchAll();
    }

    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::query($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    public static function statement(string $sql, array $bindings = []): int
    {
        return self::query($sql, $bindings)->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::connection()->lastInsertId();
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /**
     * Runs $callback inside a transaction. If anything throws, every change is undone.
     *   Database::transaction(function () { ...reserve item, lower stock... });
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function queryLog(): array
    {
        return self::$queryLog;
    }
}
