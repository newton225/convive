<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retrait de `table_count` et `seats_per_table` (accord du proprietaire du projet, CLAUDE.md
     * « Evenements ») : la capacite d'un evenement est la somme des places de ses tables, ces deux
     * colonnes n'etaient plus qu'un repli pour un evenement sans table.
     *
     * Avant de les retirer, un evenement qui n'aurait encore aucune table recoit celles que ces
     * colonnes decrivaient : sans cela il se retrouverait sans aucune place. La migration du
     * 2026-09-30 l'a deja fait ; ce passage rattrape un evenement cree entre-temps.
     */
    public function up(): void
    {
        DB::table('events')
            ->where('table_count', '>', 0)
            ->where('seats_per_table', '>', 0)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('seating_tables')->whereColumn('seating_tables.event_id', 'events.id'))
            ->select(['id', 'table_count', 'seats_per_table'])
            // Lus d'un coup, pas par pages : un evenement traite sort du filtre, une pagination
            // sauterait alors les suivants.
            ->get()
            ->each(function (object $event) {
                DB::table('seating_tables')->insert(array_map(fn (int $number) => [
                    'event_id' => $event->id,
                    'number' => $number,
                    'capacity' => $event->seats_per_table,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], range(1, (int) $event->table_count)));
            });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['table_count', 'seats_per_table']);
        });
    }

    /**
     * Reverse the migrations. Les colonnes reviennent vides : la salle reste decrite par ses
     * tables.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('table_count')->default(0);
            $table->unsignedSmallInteger('seats_per_table')->default(0);
        });
    }
};
