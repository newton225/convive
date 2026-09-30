<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cree les tables des evenements enregistres avant le plan de salle par groupes (decision du
     * 2026-09-29), qui n'en ont encore aucune : `table_count` tables de `seats_per_table` places.
     *
     * Prealable au retrait de ces deux colonnes (CLAUDE.md, « Evenements ») : une fois chaque
     * evenement pourvu de ses tables, `Event::capacity()` n'a plus besoin de son calcul de repli.
     * Un evenement qui a deja au moins une table n'est pas touche, meme si son plan differe de
     * l'ancien calcul : c'est le plan de salle qui fait foi.
     *
     * Constructeur de requetes plutot que modeles : une migration doit rejouer a l'identique
     * meme quand les modeles auront change.
     */
    public function up(): void
    {
        DB::table('events')
            ->where('table_count', '>', 0)
            ->where('seats_per_table', '>', 0)
            ->whereNotExists(fn ($query) => $query->from('seating_tables')->whereColumn('seating_tables.event_id', 'events.id'))
            ->select(['id', 'table_count', 'seats_per_table'])
            // Par identifiant, pas par decalage : un evenement traite sort du filtre, un decalage
            // sauterait alors les suivants.
            ->chunkById(100, function ($events) {
                $now = now();

                foreach ($events as $event) {
                    $rows = array_map(fn (int $number) => [
                        'event_id' => $event->id,
                        'number' => $number,
                        'capacity' => $event->seats_per_table,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], range(1, $event->table_count));

                    // Jusqu'a 2000 tables par evenement : par lots, sous la limite de parametres de SQLite.
                    foreach (array_chunk($rows, 500) as $batch) {
                        DB::table('seating_tables')->insert($batch);
                    }
                }
            });
    }

    /**
     * Rien a defaire : les tables creees sont indiscernables de celles du plan de salle, et en
     * supprimer effacerait des attributions faites depuis.
     */
    public function down(): void {}
};
