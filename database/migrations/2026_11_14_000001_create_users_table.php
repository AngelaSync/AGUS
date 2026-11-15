<?php

use Agus\Database\Migration;
use Agus\Database\Schema\Blueprint;
use Agus\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('email', 190)->unique();
            $t->string('password');
            $t->enum('role', ['customer', 'shop', 'admin'])->default('customer');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
