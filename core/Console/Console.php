<?php

declare(strict_types=1);

namespace Agus\Console;

use Agus\App;
use Agus\Database\Database;
use Agus\Database\Migrator;
use Agus\Database\Schema\Schema;

/**
 * The `php agus <command>` tool. Run `php agus` to see every command.
 */
class Console
{
    private const COMMANDS = [
        'serve' => 'Start the dev server (default http://localhost:8000). Option: --port=8080',
        'db:create' => 'Create the MySQL database named in .env (XAMPP)',
        'migrate' => 'Run new migrations',
        'migrate:status' => 'Show which migrations have run',
        'migrate:rollback' => 'Undo the last batch of migrations',
        'migrate:fresh' => 'Undo every migration, then run them all again',
        'db:seed' => 'Insert sample data (database/seeders/DatabaseSeeder.php)',
        'route:list' => 'List every route',
        'make:controller' => 'Create a controller: make:controller ListingController [--resource]',
        'make:model' => 'Create a model: make:model Listing',
        'make:middleware' => 'Create a middleware: make:middleware ShopOwnerMiddleware',
        'make:migration' => 'Create a migration: make:migration create_listings_table',
        'test' => 'Run the tests in /tests',
    ];

    public function __construct(private App $app)
    {
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $args = array_values(array_filter(array_slice($argv, 2), fn ($a) => !str_starts_with($a, '--')));
        $options = [];
        foreach (array_slice($argv, 2) as $arg) {
            if (str_starts_with($arg, '--')) {
                [$k, $v] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
                $options[$k] = $v;
            }
        }

        try {
            return match ($command) {
                'serve' => $this->serve($options),
                'db:create' => $this->dbCreate(),
                'migrate' => $this->migrate(),
                'migrate:status' => $this->migrateStatus(),
                'migrate:rollback' => $this->rollback(),
                'migrate:fresh' => $this->fresh(),
                'db:seed' => $this->seed(),
                'route:list' => $this->routeList(),
                'make:controller' => $this->makeController($args[0] ?? '', isset($options['resource'])),
                'make:model' => $this->makeModel($args[0] ?? ''),
                'make:middleware' => $this->makeMiddleware($args[0] ?? ''),
                'make:migration' => $this->makeMigration($args[0] ?? ''),
                'test' => $this->test(),
                default => $this->help($command),
            };
        } catch (\Throwable $e) {
            $this->line("\033[31mError:\033[0m " . $e->getMessage());
            return 1;
        }
    }

    private function line(string $text = ''): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    private function ok(string $text): void
    {
        $this->line("\033[32m✔\033[0m $text");
    }

    private function help(string $command): int
    {
        if ($command !== 'help') {
            $this->line("Unknown command \"$command\".\n");
        }
        $this->line("\033[1m" . config('app.name', 'AGUS') . " console\033[0m\n\nUsage: php agus <command>\n");
        foreach (self::COMMANDS as $name => $description) {
            $this->line(sprintf("  \033[33m%-18s\033[0m %s", $name, $description));
        }
        return $command === 'help' ? 0 : 1;
    }

    private function serve(array $options): int
    {
        $port = (int) ($options['port'] ?? 8000);
        $host = (string) ($options['host'] ?? 'localhost');
        $public = $this->app->basePath('public');
        $this->line("AGUS is running at \033[36mhttp://$host:$port\033[0m  (Ctrl+C to stop)");
        passthru(sprintf('%s -S %s:%d -t %s %s', escapeshellarg(PHP_BINARY), $host, $port, escapeshellarg($public), escapeshellarg($public . '/router.php')), $code);
        return (int) $code;
    }

    private function dbCreate(): int
    {
        if (config('database.driver') === 'sqlite') {
            Database::connection();
            $this->ok('SQLite database ready at ' . config('database.sqlite.database'));
            return 0;
        }
        $c = (array) config('database.mysql');
        $name = (string) $c['database'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('DB_DATABASE may only contain letters, numbers and underscores.');
        }
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $c['host'], $c['port']),
            (string) $c['username'],
            (string) $c['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->ok("Database \"$name\" is ready.");
        return 0;
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->app->basePath('database/migrations'));
    }

    private function migrate(): int
    {
        $ran = $this->migrator()->migrate();
        if (!$ran) {
            $this->line('Nothing to migrate.');
        }
        foreach ($ran as $name) {
            $this->ok("Migrated: $name");
        }
        return 0;
    }

    private function migrateStatus(): int
    {
        $m = $this->migrator();
        $ran = $m->ran();
        foreach ($m->files() as $file) {
            $this->line((in_array($file, $ran, true) ? "\033[32m Ran \033[0m " : "\033[33m Pending \033[0m ") . $file);
        }
        return 0;
    }

    private function rollback(): int
    {
        $undone = $this->migrator()->rollback();
        if (!$undone) {
            $this->line('Nothing to roll back.');
        }
        foreach ($undone as $name) {
            $this->ok("Rolled back: $name");
        }
        return 0;
    }

    private function fresh(): int
    {
        foreach ($this->migrator()->fresh() as $name) {
            $this->ok("Migrated: $name");
        }
        return 0;
    }

    private function seed(): int
    {
        $file = $this->app->basePath('database/seeders/DatabaseSeeder.php');
        if (!is_file($file)) {
            $this->line('No seeder found.');
            return 0;
        }
        if (!Schema::hasTable('users') && !Schema::hasTable('migrations')) {
            throw new \RuntimeException('Run "php agus migrate" first.');
        }
        $seeder = require $file;
        Database::transaction(fn () => $seeder());
        $this->ok('Database seeded.');
        return 0;
    }

    private function routeList(): int
    {
        $this->app->loadRoutes();
        $rows = [];
        foreach ($this->app->router()->routes() as $route) {
            $rows[] = [implode('|', $route->getMethods()), $route->getPattern(), $route->getName() ?? '', $route->handlerName(), implode(', ', $route->getMiddleware())];
        }
        $widths = [6, 4, 4, 6, 10];
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen($cell));
            }
        }
        $fmt = implode('  ', array_map(fn ($w) => "%-{$w}s", $widths));
        $this->line("\033[1m" . sprintf($fmt, 'Method', 'URI', 'Name', 'Action', 'Middleware') . "\033[0m");
        foreach ($rows as $row) {
            $this->line(sprintf($fmt, ...$row));
        }
        return 0;
    }

    // ---- generators ---------------------------------------------------------

    private function create(string $path, string $contents): int
    {
        $full = $this->app->basePath($path);
        if (file_exists($full)) {
            throw new \RuntimeException("$path already exists.");
        }
        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0775, true);
        }
        file_put_contents($full, $contents);
        $this->ok("Created $path");
        return 0;
    }

    private function className(string $name, string $kind): string
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            throw new \InvalidArgumentException("Give the $kind a name in PascalCase, e.g. php agus make:$kind Listing");
        }
        return $name;
    }

    private function makeController(string $name, bool $resource): int
    {
        $name = $this->className($name, 'controller');
        $methods = $resource ? <<<'PHP'

    public function index(): Response
    {
        return $this->view('...', []);
    }

    public function create(): Response
    {
        return $this->view('...', []);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            // 'name' => 'required|max:80',
        ]);
        return $this->redirect('/')->with('success', 'Saved.');
    }

    public function show(int $id): Response
    {
        return $this->view('...', []);
    }

    public function edit(int $id): Response
    {
        return $this->view('...', []);
    }

    public function update(Request $request, int $id): Response
    {
        return $this->redirect('/')->with('success', 'Updated.');
    }

    public function destroy(int $id): Response
    {
        return $this->redirect('/')->with('success', 'Deleted.');
    }
PHP : <<<'PHP'

    public function index(Request $request): Response
    {
        return $this->view('...', []);
    }
PHP;
        return $this->create("app/Controllers/$name.php", <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use Agus\Controller;
use Agus\Http\Request;
use Agus\Http\Response;

class $name extends Controller
{{$methods}
}

PHP);
    }

    private function makeModel(string $name): int
    {
        $name = $this->className($name, 'model');
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
        return $this->create("app/Models/$name.php", <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Agus\Database\Model;

class $name extends Model
{
    protected static string \$table = '$table';

    protected static array \$fillable = [];
}

PHP);
    }

    private function makeMiddleware(string $name): int
    {
        $name = $this->className($name, 'middleware');
        return $this->create("app/Middleware/$name.php", <<<PHP
<?php

declare(strict_types=1);

namespace App\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Middleware\Middleware;

class $name implements Middleware
{
    public function handle(Request \$request, callable \$next, string ...\$args): Response
    {
        // Return a Response here to stop the request, or continue:
        return \$next(\$request);
    }
}

PHP);
    }

    private function makeMigration(string $name): int
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Use snake_case, e.g. php agus make:migration create_listings_table');
        }
        $table = preg_match('/^create_(\w+)_table$/', $name, $m) ? $m[1] : 'table_name';
        $file = 'database/migrations/' . date('Y_m_d_His') . "_$name.php";
        return $this->create($file, <<<PHP
<?php

use Agus\Database\Migration;
use Agus\Database\Schema\Blueprint;
use Agus\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('$table', function (Blueprint \$t) {
            \$t->id();
            // \$t->string('name', 80);
            // \$t->foreignId('user_id')->references('users')->onDelete('cascade');
            \$t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('$table');
    }
};

PHP);
    }

    private function test(): int
    {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->app->basePath('tests/run.php')), $code);
        return (int) $code;
    }
}
