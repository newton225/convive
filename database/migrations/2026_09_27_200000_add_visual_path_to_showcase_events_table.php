<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emplacement du visuel de l'evenement annonce, sur le disque `tenant_media`. Le chemin, jamais
 * une URL : les URL de ces fichiers sont signees et expirent, la vitrine en signe une neuve a
 * chaque affichage sans avoir a ouvrir la base du locataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showcase_events', function (Blueprint $table) {
            $table->string('visual_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('showcase_events', function (Blueprint $table) {
            $table->dropColumn('visual_path');
        });
    }
};
