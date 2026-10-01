<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le motif d'un acces de support (README section 3) : pourquoi le Proprietaire l'ouvre.
     * Obligatoire a l'ouverture ; nul seulement sur les acces ouverts avant cette colonne.
     */
    public function up(): void
    {
        Schema::table('support_access_grants', function (Blueprint $table) {
            $table->text('reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_access_grants', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
