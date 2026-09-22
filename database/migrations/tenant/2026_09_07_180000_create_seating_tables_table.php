<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les tables physiques d'un evenement (README 2.6, ecran 21), etape 6 de « Ordre de
     * construction ».
     *
     * La capacite de l'evenement reste derivee de `table_count x seats_per_table`
     * (`Event::capacity()`) : ces lignes ne la redefinissent pas, elles la rendent concrete,
     * une table a la fois, pour que l'attribution ait quelque chose a designer. Provisionnees
     * paresseusement par `App\Actions\Seating\AssignTable` plutot qu'a la sauvegarde de
     * l'evenement : `table_count` peut encore changer avant le premier invite confirme.
     *
     * Table de la base d'un locataire (voir CLAUDE.md, « Multi-locataire ») : pas de colonne
     * `tenant_id`, la base elle-meme est la frontiere.
     */
    public function up(): void
    {
        Schema::create('seating_tables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('capacity');

            // Table reservee a une unite (ex. table 01 pour ETAT MAJOR, README 2.6). Jamais de
            // suppression en cascade : une unite retiree se desactive, elle ne disparait pas
            // (voir CLAUDE.md, « Unites »).
            $table->foreignId('reserved_unit_id')->nullable()->constrained('units');

            $table->timestamps();

            $table->unique(['event_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seating_tables');
    }
};
