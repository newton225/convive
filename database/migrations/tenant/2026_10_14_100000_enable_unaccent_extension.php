<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voir la migration du meme nom dans `database/migrations` : `unaccent` s'active base par base, donc
 * dans celle de chaque organisation aussi. Sans effet sur SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::connection($this->getConnection())->statement('create extension if not exists unaccent');
        }
    }

    public function down(): void
    {
        // Laissee en place, comme cote central.
    }
};
