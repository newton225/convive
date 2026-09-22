<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Les unites sont des donnees de locataire, pas des constantes du code : chaque
     * organisation compose la sienne. Le participant et chacun de ses accompagnateurs en
     * choisissent une, obligatoirement (README 2.5).
     *
     * Table de la base d'un locataire (voir CLAUDE.md, « Multi-locataire ») : pas de colonne
     * `tenant_id`, la base elle-meme est la frontiere.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
