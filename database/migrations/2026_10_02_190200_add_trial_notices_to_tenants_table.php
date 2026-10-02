<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les rappels de fin d'essai (README section 3) : sept jours avant, la veille, puis le jour ou
 * l'essai a pris fin. Une colonne « envoye a » par rappel, jamais un statut : la tache planifiee
 * rejouee chaque jour ne previent pas deux fois (CLAUDE.md, « Idempotence »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_ending_notified_at')->nullable();
            $table->timestamp('trial_last_day_notified_at')->nullable();
            $table->timestamp('trial_ended_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['trial_ending_notified_at', 'trial_last_day_notified_at', 'trial_ended_notified_at']);
        });
    }
};
