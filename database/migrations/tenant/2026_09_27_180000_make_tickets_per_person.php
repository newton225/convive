<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un billet par personne (README 2.8, decision du proprietaire du 2026-09-27) : l'invite
 * (`holder_position` 0) et chacun de ses accompagnateurs (1, 2...) ont leur billet et leur QR.
 *
 * L'unicite passe de « un billet par inscription » a « un billet par personne d'une inscription ».
 * C'est la seule modification d'une structure existante de ce changement, accordee avec le choix
 * de l'option A par le proprietaire. Les billets deja emis deviennent ceux des invites principaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedSmallInteger('holder_position')->default(0);
            $table->string('holder_name')->nullable();
            $table->foreignId('holder_unit_id')->nullable()->constrained('units')->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['registration_id']);
            $table->unique(['registration_id', 'holder_position']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['registration_id', 'holder_position']);
            $table->unique('registration_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holder_unit_id');
            $table->dropColumn(['holder_position', 'holder_name']);
        });
    }
};
