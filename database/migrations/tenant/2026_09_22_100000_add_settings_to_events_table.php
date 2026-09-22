<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * README ecran 24 : rappels et regles d'un evenement. Toutes par defaut a vrai : c'est le
     * comportement deja en place avant ces colonnes (README 2.7), une organisation choisit d'en
     * desactiver, jamais l'inverse a l'insu de son createur.
     *
     * Trois regles (`rule_allow_without_proof`, `rule_proof_legibility`, `rule_temporary_hold`)
     * sont posees ici mais pas encore appliquees ailleurs dans le code : leur sens exact n'est
     * pas assez precis dans le README pour deviner sans risquer une regle fausse (voir
     * `EventSettingsController`). Elles s'enregistrent, l'ecran le dit, aucune tache ni action ne
     * les lit encore.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('reminder_j7_enabled')->default(true);
            $table->boolean('reminder_j2_enabled')->default(true);
            $table->boolean('reminder_j1_enabled')->default(true);
            $table->boolean('reminder_day_of_enabled')->default(true);

            $table->boolean('rule_scheduled_send')->default(true);
            $table->boolean('rule_auto_seating')->default(true);
            $table->boolean('rule_allow_without_proof')->default(true);
            $table->boolean('rule_proof_legibility')->default(true);
            $table->boolean('rule_purge_on_exhaustion')->default(true);
            $table->boolean('rule_temporary_hold')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_j7_enabled', 'reminder_j2_enabled', 'reminder_j1_enabled', 'reminder_day_of_enabled',
                'rule_scheduled_send', 'rule_auto_seating', 'rule_allow_without_proof',
                'rule_proof_legibility', 'rule_purge_on_exhaustion', 'rule_temporary_hold',
            ]);
        });
    }
};
