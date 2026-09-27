<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur mensuel des messages envoyes aux invites de l'organisation (cartes, rappels), une ligne
 * par mois. `quota_alerted_at` evite de prevenir l'abonnement plus d'une fois par mois quand le
 * plafond du plan est atteint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_usages', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique();
            $table->unsignedInteger('count')->default(0);
            $table->timestamp('quota_alerted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_usages');
    }
};
