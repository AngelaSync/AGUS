<?php

declare(strict_types=1);

namespace Agus\Database;

use Agus\Database\Schema\Blueprint;
use Agus\Database\Schema\Schema;

/**
 * Runs the files in database/migrations in filename order and remembers
 * which ones already ran (in the `migrations` table), grouped into batches
 * so `rollback` can undo the last run.
 */
class Migrator
{
    public function __construct(private string $dir)
    {
    }

    private function ensureTable(): void
    {
        if (!Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $t) {
                $t->id();
                $t->string('migration');
                $t->integer('batch');
                $t->dateTime('ran_at')->nullable();
            });
        }
    }

    /** @return string[] filenames without .php, sorted */
    public function files(): array
    {
        $files = array_map(fn ($f) => basename($f, '.php'), glob($this->dir . '/*.php') ?: []);
        sort($files);
        return $files;
    }

    public function ran(): array
    {
        $this->ensureTable();
        return array_column(Database::table('migrations')->orderBy('id')->get(), 'migration');
    }

    /** @return string[] names of migrations that ran */
    public function migrate(): array
    {
        $this->ensureTable();
        $pending = array_values(array_diff($this->files(), $this->ran()));
        if (!$pending) {
            return [];
        }

        $batch = (int) (Database::selectOne('SELECT MAX(batch) AS b FROM migrations')['b'] ?? 0) + 1;
        foreach ($pending as $name) {
            $this->load($name)->up();
            Database::table('migrations')->insert(['migration' => $name, 'batch' => $batch, 'ran_at' => date('Y-m-d H:i:s')]);
        }
        return $pending;
    }

    /** Undoes the last batch. @return string[] */
    public function rollback(): array
    {
        $this->ensureTable();
        $batch = (int) (Database::selectOne('SELECT MAX(batch) AS b FROM migrations')['b'] ?? 0);
        if ($batch === 0) {
            return [];
        }
        $rows = Database::table('migrations')->where('batch', $batch)->orderBy('id', 'desc')->get();
        foreach ($rows as $row) {
            if (is_file($this->dir . '/' . $row['migration'] . '.php')) {
                $this->load($row['migration'])->down();
            }
            Database::table('migrations')->where('id', $row['id'])->delete();
        }
        return array_column($rows, 'migration');
    }

    /** Rolls everything back, then migrates again. */
    public function fresh(): array
    {
        while ($this->rollback()) {
        }
        return $this->migrate();
    }

    private function load(string $name): Migration
    {
        $migration = require $this->dir . '/' . $name . '.php';
        if (!$migration instanceof Migration) {
            throw new \RuntimeException("$name.php must return a Migration object.");
        }
        return $migration;
    }
}
