<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les visites guidees deja terminees par le membre : suivies en base plutot que dans le navigateur,
 * pour qu'une visite ne redemarre pas d'elle-meme sur chaque nouvel appareil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('completed_tours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('completed_tours');
        });
    }
};
