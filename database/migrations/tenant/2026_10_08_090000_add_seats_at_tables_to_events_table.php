<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Un evenement sans table, comme un rassemblement en plein air (decision du proprietaire du
     * projet, 2026-10-08) : les invites ne sont assis a aucune table. La capacite reste derivee du
     * plan de la salle, qui ne porte alors qu'une table interne du nombre de places saisi. Les
     * evenements existants gardent leurs tables.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('seats_at_tables')->default(true)->after('venue_map_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('seats_at_tables');
        });
    }
};
