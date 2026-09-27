<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre de reservations laissees expirer puis relancees sur la meme inscription (SECURITY.md C3) :
 * `held_until` est reecrit a chaque relance, l'expiration precedente disparaitrait sans ce compteur
 * et le delai croissant par numero pourrait etre contourne en relancant toujours la meme inscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedInteger('lapsed_holds_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('lapsed_holds_count');
        });
    }
};
