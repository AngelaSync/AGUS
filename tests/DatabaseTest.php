<?php

use App\Models\User;
use Agus\Database\Database;
use Agus\Database\Migrator;
use Agus\Database\Schema\Blueprint;
use Agus\Database\Schema\Schema;

return [
    'migrations create and roll back tables' => function () {
        $m = new Migrator(BASE_PATH . '/database/migrations');
        T::assertTrue(count($m->migrate()) >= 1, 'should run at least one migration');
        T::assertTrue(Schema::hasTable('users'));
        T::assertSame([], $m->migrate(), 'second run should do nothing');
        $m->rollback();
        T::assertTrue(!Schema::hasTable('users'), 'users table should be gone');
    },

    'query builder inserts, filters, updates and deletes' => function () {
        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('price', 8, 2);
            $t->integer('qty')->default(0);
        });
        $q = fn () => Database::table('items');
        $q()->insert(['name' => 'Pandesal', 'price' => 25, 'qty' => 5]);
        $q()->insert(['name' => 'Ensaymada', 'price' => 60, 'qty' => 2]);
        $id = $q()->insert(['name' => 'Hotcake', 'price' => 30, 'qty' => 0]);

        T::assertSame(2, $q()->where('price', '<', 50)->count());
        T::assertSame('Ensaymada', $q()->orderBy('price', 'desc')->first()['name']);
        T::assertSame(1, $q()->search(['name'], 'pande')->count());
        T::assertSame(1, $q()->where('id', $id)->update(['qty' => 4]));
        $q()->where('id', $id)->increment('qty', -1);
        T::assertSame(3, (int) $q()->find($id)['qty']);
        T::assertSame(1, $q()->where('name', 'Hotcake')->delete());
        T::assertSame(2, $q()->count());
    },

    'query builder refuses unsafe identifiers and wide updates' => function () {
        T::assertThrows(\InvalidArgumentException::class, fn () => Database::table('users; DROP TABLE users'));
        T::assertThrows(\InvalidArgumentException::class, fn () => Database::table('users')->orderBy('name; --'));
        T::assertThrows(\LogicException::class, fn () => Database::table('users')->update(['name' => 'x']));
        T::assertThrows(\LogicException::class, fn () => Database::table('users')->delete());
    },

    'values are bound, not pasted into SQL' => function () {
        $sql = Database::table('users')->where('email', "x' OR '1'='1")->toSql();
        T::assertSame('SELECT * FROM users WHERE email = ? LIMIT 1', Database::table('users')->where('email', 'x')->limit(1)->toSql());
        T::assertTrue(!str_contains($sql, "OR '1'"), 'input must not appear in SQL');
    },

    'paginate splits results into pages' => function () {
        Schema::create('nums', fn (Blueprint $t) => $t->integer('n'));
        for ($i = 1; $i <= 23; $i++) {
            Database::table('nums')->insert(['n' => $i]);
        }
        $page = Database::table('nums')->orderBy('n')->paginate(10, 3);
        T::assertSame(23, $page['total']);
        T::assertSame(3, $page['last_page']);
        T::assertSame(3, count($page['data']));
        T::assertSame(21, (int) $page['data'][0]['n']);
    },

    'transactions roll back on errors' => function () {
        Schema::create('logs', fn (Blueprint $t) => $t->string('msg'));
        try {
            Database::transaction(function () {
                Database::table('logs')->insert(['msg' => 'first']);
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }
        T::assertSame(0, Database::table('logs')->count());
    },

    'models create, find, update and hide fields' => function () {
        (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
        $user = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'hash', 'role' => 'customer', 'is_admin' => 1]);
        T::assertTrue(is_int($user->id) && $user->id > 0, 'should get an id');
        T::assertSame(null, User::find($user->id)->is_admin, '$fillable blocks unknown columns');
        $user->update(['name' => 'Ana R.']);
        T::assertSame('Ana R.', User::findByEmail('ANA@example.com')->name);
        T::assertTrue(!array_key_exists('password', User::find($user->id)->toArray()), 'password is hidden');
        T::assertSame(1, count(User::where('role', 'customer')->get()));
    },

    'schema builder writes MySQL SQL' => function () {
        $sql = Schema::toSql('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_id')->references('shops')->onDelete('cascade');
            $t->string('name', 80);
            $t->enum('status', ['live', 'sold_out'])->default('live');
        }, 'mysql')[0];
        T::assertContains('id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $sql);
        T::assertContains('name VARCHAR(80) NOT NULL', $sql);
        T::assertContains("status ENUM('live', 'sold_out') NOT NULL DEFAULT 'live'", $sql);
        T::assertContains('FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE', $sql);
    },
];
