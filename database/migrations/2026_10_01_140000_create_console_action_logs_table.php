<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le journal central de la console (README section 3 et ecran 33) : acteur, organisation
     * concernee, avant et apres, adresse IP. Ecriture seule, conservation 24 mois.
     *
     * Le nom de l'organisation est recopie sur la ligne et `tenant_id` ne porte pas de contrainte :
     * le journal garde la trace d'une organisation supprimee, sans ses donnees.
     */
    public function up(): void
    {
        Schema::create('console_action_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('organisation')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('console_action_logs');
    }
};
