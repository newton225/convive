<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * README 2.5 (ecran 4) : l'email est facultatif, contrairement au telephone. WhatsApp reste
     * le canal systematique de la carte d'invitation (2.7), l'email s'y ajoute quand l'invite
     * l'a renseigne. Decision du proprietaire du projet, etape 8 de « Ordre de construction » :
     * le formulaire d'inscription ne demandait a l'origine aucune adresse, ce qui rendait
     * l'envoi par email de 2.7 impossible a honorer.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
