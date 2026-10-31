<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision du 2026-10-31 : l'envoi de la carte part par la file, `card_sent_at` dit seulement qu'il a ete
     * mis en file. Quand le service d'envoi refuse le message, l'echec est marque ici pour que la liste des
     * inscrits le montre ; un envoi reussi ensuite le remet a zero.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('card_failed_at')->nullable()->after('card_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('card_failed_at');
        });
    }
};
