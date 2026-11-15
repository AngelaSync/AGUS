<?php

use Agus\Database\Migration;
use Agus\Database\Schema\Blueprint;
use Agus\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->references('users')->onDelete('cascade');
            $t->foreignId('listing_id')->references('listings')->onDelete('cascade');
            $t->integer('quantity');
            $t->decimal('total', 10, 2);
            $t->string('pickup_code', 12)->unique();
            $t->enum('status', ['reserved', 'picked_up', 'cancelled'])->default('reserved');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
