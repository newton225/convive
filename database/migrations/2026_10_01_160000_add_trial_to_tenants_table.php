<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La periode d'essai d'une organisation (README section 3). `trial_started_at` dit qu'un essai
     * a ete ouvert ; `trial_ends_at` nul veut dire « sans date de fin », la regle du moment
     * (decision du proprietaire du projet, 2026-10-01).
     *
     * Les organisations deja ouvertes recoivent le meme essai que les nouvelles, depuis leur
     * creation : sans cela, deux espaces identiques n'auraient pas les memes droits selon leur
     * date d'ouverture.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });

        DB::table('tenants')->whereNull('trial_started_at')->update(['trial_started_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['trial_started_at', 'trial_ends_at']);
        });
    }
};
