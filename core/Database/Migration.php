<?php

declare(strict_types=1);

namespace Agus\Database;

/**
 * Every file in database/migrations returns one of these:
 *
 *   return new class extends Migration {
 *       public function up(): void   { Schema::create('shops', fn (Blueprint $t) => ...); }
 *       public function down(): void { Schema::dropIfExists('shops'); }
 *   };
 */
abstract class Migration
{
    abstract public function up(): void;

    abstract public function down(): void;
}
