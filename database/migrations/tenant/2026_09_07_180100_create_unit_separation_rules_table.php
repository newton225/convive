<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les regles de separation entre unites (README 2.6) : une option par evenement, pas un
     * reglage de locataire. Deux unites ne partagent alors jamais une table.
     *
     * `unit_a_id` est toujours le plus petit identifiant des deux (voir
     * `App\Models\UnitSeparationRule::canonicalPair()`) : la paire est non ordonnee, canoniser
     * l'ordre a l'ecriture rend la contrainte d'unicite possible sans dupliquer chaque regle
     * dans les deux sens.
     */
    public function up(): void
    {
        Schema::create('unit_separation_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_a_id')->constrained('units');
            $table->foreignId('unit_b_id')->constrained('units');

            $table->timestamps();

            $table->unique(['event_id', 'unit_a_id', 'unit_b_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_separation_rules');
    }
};
