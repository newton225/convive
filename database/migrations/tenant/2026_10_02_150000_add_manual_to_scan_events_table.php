<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entree validee sans scan (README ecran 26) : l'agent a retrouve l'invite par sa reference ou son
 * nom parce que le QR ne pouvait pas etre lu. Le journal des passages distingue ces entrees : le QR
 * prouve que l'invite detient son billet, une recherche par nom ne prouve que la parole de l'agent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_events', function (Blueprint $table) {
            $table->boolean('manual')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('scan_events', function (Blueprint $table) {
            $table->dropColumn('manual');
        });
    }
};
