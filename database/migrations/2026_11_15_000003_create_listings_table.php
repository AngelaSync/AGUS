<?php

use Agus\Database\Migration;
use Agus\Database\Schema\Blueprint;
use Agus\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_id')->references('shops')->onDelete('cascade');
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->enum('category', ['bread', 'pastries', 'meals']);
            $t->decimal('original_price', 10, 2);
            $t->decimal('price', 10, 2);
            $t->integer('quantity');
            $t->string('allergens', 160)->nullable();
            $t->string('photo', 255)->nullable();
            $t->date('best_before');
            $t->string('pickup_window', 60);
            $t->timestamps();
            $t->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
