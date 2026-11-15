<?php
/**
 * Test runner: php agus test   (or php tests/run.php)
 * Uses an in-memory SQLite database, so your real data is never touched.
 */

declare(strict_types=1);

$app = require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/TestCase.php';

use Agus\Database\Database;
use Agus\Support\Config;

Config::set('app.debug', false);
Config::set('app.log_requests', false);
Config::set('database.driver', 'sqlite');
Config::set('database.sqlite.database', ':memory:');

$passed = 0;
$failed = 0;
$start = microtime(true);

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    $tests = require $file;
    echo "\n\033[1m" . basename($file, '.php') . "\033[0m\n";

    foreach ($tests as $name => $test) {
        // Fresh database and session for every test
        Database::setConnection(Database::connect(['driver' => 'sqlite', 'sqlite' => ['database' => ':memory:']]));
        $_SESSION = [];
        Agus\Support\Auth::reset();

        try {
            $test();
            $passed++;
            echo "  \033[32m✔\033[0m $name\n";
        } catch (\Throwable $e) {
            $failed++;
            echo "  \033[31m✘ $name\033[0m\n    " . $e->getMessage() . "\n    at " . basename($e->getFile()) . ':' . $e->getLine() . "\n";
        }
    }
}

printf(
    "\n%s%d passed, %d failed\033[0m (%d assertions, %.0f ms)\n",
    $failed ? "\033[31m" : "\033[32m",
    $passed,
    $failed,
    T::$assertions,
    (microtime(true) - $start) * 1000
);

exit($failed ? 1 : 0);
