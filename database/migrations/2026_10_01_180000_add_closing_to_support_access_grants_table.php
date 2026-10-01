<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La fin d'un acces de support (README section 3).
     *
     * - `finished_at` et `closing_note` : la personne de l'equipe Convive ferme elle-meme l'acces
     *   quand elle a termine, avec ce qu'elle a constate.
     * - `ended_notified_at` : les Proprietaires ont ete prevenus de la fin. Une colonne « envoye a »
     *   plutot qu'un statut, pour que la tache qui annonce les echeances ne previenne qu'une fois.
     */
    public function up(): void
    {
        Schema::table('support_access_grants', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamp('ended_notified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_access_grants', function (Blueprint $table) {
            $table->dropColumn(['finished_at', 'closing_note', 'ended_notified_at']);
        });
    }
};
