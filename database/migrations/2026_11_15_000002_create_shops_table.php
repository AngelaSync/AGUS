<?php

use Agus\Database\Migration;
use Agus\Database\Schema\Blueprint;
use Agus\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->references('users')->onDelete('cascade');
            $t->string('name', 120);
            $t->string('address', 255);
            // Filled in by GeocodingService (OpenStreetMap), or by the admin by hand.
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('is_verified')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
