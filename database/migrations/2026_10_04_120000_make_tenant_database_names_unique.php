<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le nom du fichier de base d'une organisation est garde sur elle et unique (decision du
 * proprietaire du projet, 2026-10-04) : les nouvelles recoivent un nom tire au hasard
 * (`TenantDatabaseName`), et la base refuse que deux organisations designent le meme fichier.
 *
 * Une organisation qui n'aurait pas son nom enregistre recoit celui qu'elle utilise deja
 * (`tenant<numero>.sqlite`) : le nom n'est plus jamais calcule a partir du numero, qui peut etre redonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->whereNull('tenancy_db_name')
            ->orderBy('id')
            ->each(fn (object $tenant) => DB::table('tenants')->where('id', $tenant->id)->update([
                'tenancy_db_name' => config('tenancy.database.prefix').$tenant->id.config('tenancy.database.suffix'),
            ]));

        Schema::table('tenants', function (Blueprint $table) {
            $table->unique('tenancy_db_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['tenancy_db_name']);
        });
    }
};
