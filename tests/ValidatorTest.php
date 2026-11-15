<?php

use Agus\Database\Migrator;
use Agus\Database\Database;
use Agus\Support\Validator;

return [
    'required, email and length rules' => function () {
        $v = Validator::make(['name' => '', 'email' => 'nope', 'bio' => str_repeat('a', 11)], [
            'name' => 'required',
            'email' => 'required|email',
            'bio' => 'max:10',
        ]);
        T::assertTrue($v->fails());
        T::assertSame(['name', 'email', 'bio'], array_keys($v->errors()));
    },

    'numeric min/max compare values, not lengths' => function () {
        T::assertTrue(Validator::make(['price' => '25'], ['price' => 'required|numeric|min:1|max:100'])->passes());
        T::assertTrue(Validator::make(['price' => '0'], ['price' => 'required|numeric|min:1'])->fails());
    },

    'confirmed and in rules' => function () {
        T::assertTrue(Validator::make(['password' => 'secret123', 'password_confirmation' => 'secret12'], ['password' => 'confirmed'])->fails());
        T::assertTrue(Validator::make(['role' => 'shop'], ['role' => 'in:customer,shop'])->passes());
        T::assertTrue(Validator::make(['role' => 'admin'], ['role' => 'in:customer,shop'])->fails());
    },

    'optional empty fields are skipped' => function () {
        T::assertTrue(Validator::make(['phone' => ''], ['phone' => 'numeric|min:11'])->passes());
    },

    'unique checks the database' => function () {
        (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
        Database::table('users')->insert(['name' => 'A', 'email' => 'a@x.com', 'password' => 'h', 'role' => 'customer']);
        T::assertTrue(Validator::make(['email' => 'a@x.com'], ['email' => 'unique:users,email'])->fails());
        T::assertTrue(Validator::make(['email' => 'b@x.com'], ['email' => 'unique:users,email'])->passes());
    },

    'validated() returns only fields with rules' => function () {
        $v = Validator::make(['name' => 'Ana', 'is_admin' => '1'], ['name' => 'required']);
        T::assertSame(['name' => 'Ana'], $v->validated());
    },
];
