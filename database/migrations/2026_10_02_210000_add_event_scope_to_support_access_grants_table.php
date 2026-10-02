<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un acces de support peut etre limite a un seul evenement (decision du proprietaire du projet,
 * 2026-10-02) : la personne de l'equipe Convive ne lit alors que cet evenement.
 *
 * `event_id` sans cle etrangere : l'evenement vit dans la base de l'organisation, l'acces dans la
 * base centrale. `event_name` garde le nom tel qu'il etait a l'ouverture : l'historique des acces
 * reste lisible sans ouvrir la base de l'organisation, et apres la suppression de l'evenement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_access_grants', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('event_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_access_grants', function (Blueprint $table) {
            $table->dropColumn(['event_id', 'event_name']);
        });
    }
};
