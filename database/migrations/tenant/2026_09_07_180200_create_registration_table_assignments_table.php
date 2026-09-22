<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'attribution d'une inscription a une table (README 2.6), etape 6 de « Ordre de
     * construction ». Une ligne par inscription, jamais plus : un accompagnateur est toujours
     * assis avec son invitant, l'attribution se fait donc a l'echelle du groupe entier, pas
     * siege par siege.
     *
     * `registration_id` unique : la contrainte d'unicite en base contre la double attribution
     * (SECURITY.md H4) porte ici, plutot qu'un `seat_index` par occupant, qui n'a de sens que
     * pour le plan de salle a construire (README ecran 21, non encore fait).
     */
    public function up(): void
    {
        Schema::create('registration_table_assignments', function (Blueprint $table) {
            $table->id();

            // `unique()` avant `constrained()` : appele apres, il se poserait sur l'objet de
            // contrainte de cle etrangere plutot que sur la colonne, et la grammaire l'ignore
            // silencieusement (aucune erreur a la migration, aucune contrainte reelle en base).
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('seating_table_id')->constrained()->cascadeOnDelete();
            $table->boolean('assigned_manually')->default(false);

            $table->timestamps();

            $table->index('seating_table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_table_assignments');
    }
};
