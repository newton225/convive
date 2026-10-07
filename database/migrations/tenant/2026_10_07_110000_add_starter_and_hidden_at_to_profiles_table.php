<?php

use App\Enums\StarterProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Les profils de base (Tresorier, Hotesse, Lecture) deviennent figes et masquables (decision du
     * proprietaire du projet, 2026-10-07). `starter` les reconnait sans dependre de leur nom ;
     * `hidden_at` les retire des choix proposes, sans toucher a ceux qui les portent.
     *
     * Les profils deja ouverts sont reconnus a leur nom. Leurs permissions d'origine leur sont
     * rendues par `tenants:sync-permissions` (`SyncPermissionCatalogue`), qui suit `migrate` a chaque
     * deploiement : une migration ne doit pas dependre du catalogue du code.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('starter')->nullable()->unique();
            $table->timestamp('hidden_at')->nullable();
        });

        foreach (StarterProfile::cases() as $starter) {
            DB::table('profiles')
                ->where('is_system', false)
                ->where('name', $starter->profileName())
                ->update(['starter' => $starter->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique(['starter']);
            $table->dropColumn(['starter', 'hidden_at']);
        });
    }
};
