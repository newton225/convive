<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le poste de controle d'un passage (« Entree principale », « Porte VIP ») : le journal des passages
 * dit qui a scanne, quand, et a quel poste (prototype Convive.dc.html, ecran de scan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_events', function (Blueprint $table) {
            $table->string('station', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scan_events', function (Blueprint $table) {
            $table->dropColumn('station');
        });
    }
};
