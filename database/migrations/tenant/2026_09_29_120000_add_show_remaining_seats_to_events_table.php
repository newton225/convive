<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Afficher ou non le nombre de places restantes aux invites : reglage par evenement, masque par
 * defaut (decision du proprietaire du projet, 2026-09-29). « Complet » reste toujours signale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('rule_show_remaining_seats')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('rule_show_remaining_seats');
        });
    }
};
