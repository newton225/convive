<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La fenetre d'entree d'un billet (decision du proprietaire du projet, 2026-10-09) : combien de
     * minutes avant le debut les portes ouvrent (nul : sans limite) et combien de minutes apres la
     * fin le billet vaut encore (30 au depart).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('entry_opens_minutes_before')->nullable();
            $table->unsignedSmallInteger('entry_grace_minutes')->default(30);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['entry_opens_minutes_before', 'entry_grace_minutes']);
        });
    }
};
