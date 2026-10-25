<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chainage par empreinte de l'historique d'un dossier, une chaine par dossier (ou par entree de liste
 * d'attente) : les lignes partent avec leur dossier, une chaine globale se romprait a chaque purge.
 * Modifier ou retirer une ligne au milieu d'une chaine se detecte (`RegistrationChain`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_events', function (Blueprint $table) {
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registration_events', function (Blueprint $table) {
            $table->dropColumn(['previous_hash', 'hash']);
        });
    }
};
