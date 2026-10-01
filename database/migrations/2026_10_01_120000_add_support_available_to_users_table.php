<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acces du support (README section 3) : une personne de l'equipe Convive n'apparait dans la liste
 * proposee aux organisations que si elle s'y est rendue visible. Faux par defaut : sans cela, tout
 * Proprietaire lirait les noms de toute l'equipe, de quoi se faire passer pour l'un d'eux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('support_available')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('support_available');
        });
    }
};
