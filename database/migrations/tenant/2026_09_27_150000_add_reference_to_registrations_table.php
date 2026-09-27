<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference de dossier lisible (« SP-2026-0008 », prototype Convive.dc.html), plus le compteur
 * annuel qui la numerote. Un compteur plutot que le plus grand numero existant : une inscription
 * purgee libererait sinon son numero, et deux personnes finiraient par porter la meme reference.
 * Affichage seulement (SECURITY.md H3) : la reference ne donne jamais acces a un dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('reference', 24)->nullable()->unique();
        });

        Schema::create('registration_reference_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_reference_counters');

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};
