<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marqueurs d'envoi des alertes « evenement termine » et « inscriptions closes » : une colonne
 * « envoye a » par alerte, comme `purge_notice_sent_at`, pour qu'une tache rejouee ne previenne
 * pas deux fois l'equipe. Les evenements deja termines ou clos a l'installation sont marques
 * d'emblee : l'alerte annonce un fait nouveau, pas un passe que l'equipe connait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('ended_alerted_at')->nullable();
            $table->timestamp('registrations_closed_alerted_at')->nullable();
        });

        DB::table('events')
            ->whereRaw('coalesce(ends_at, starts_at) < ?', [now()])
            ->update(['ended_alerted_at' => now(), 'registrations_closed_alerted_at' => now()]);

        DB::table('events')
            ->where('registration_deadline', '<', now())
            ->update(['registrations_closed_alerted_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['ended_alerted_at', 'registrations_closed_alerted_at']);
        });
    }
};
