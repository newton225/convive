<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marqueurs d'envoi des alertes d'anticipation (« Il reste N places », « Purge programmee ») : une
 * colonne « envoye a » par alerte, comme `card_sent_at`, pour qu'une tache rejouee ou une nouvelle
 * reservation ne previenne pas deux fois l'equipe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('seats_low_alerted_at')->nullable();
            $table->timestamp('purge_notice_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['seats_low_alerted_at', 'purge_notice_sent_at']);
        });
    }
};
