<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limites propres a une organisation (decision du proprietaire du projet, 2026-10-02) : un plan
 * vendu sur devis ne donne pas les memes chiffres a tous ses clients. Une colonne remplie remplace
 * la limite du plan pour cette organisation seulement ; une colonne nulle suit le plan.
 *
 * Base centrale, comme `subscriptions` et `tenant_usages` : la console lit ces limites a travers
 * les organisations, sans ouvrir leur base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_active_events')->nullable();
            $table->unsignedInteger('max_registrations')->nullable();
            $table->unsignedInteger('max_members')->nullable();
            $table->unsignedInteger('max_messages_per_month')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_limits');
    }
};
