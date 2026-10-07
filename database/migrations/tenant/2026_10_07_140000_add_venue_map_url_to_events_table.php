<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le lien de localisation du lieu, facultatif (decision du proprietaire du projet,
     * 2026-10-07) : l'invite l'ouvre dans l'application de cartes de son telephone. Un lien vers un
     * service de cartes connu seulement (`App\Support\MapLink`), jamais une carte chargee dans nos
     * pages.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('venue_map_url', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('venue_map_url');
        });
    }
};
