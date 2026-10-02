<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ce que l'ecran « Securite » de la console montre (README section 3) : les blocages par limite
     * de debit et les verrouillages de connexion, et le resultat de la verification quotidienne de
     * chaque journal d'audit. Base centrale : ces faits se lisent a travers les organisations.
     *
     * `tenant_id` ne porte pas de contrainte : la trace survit a l'effacement d'une organisation.
     */
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            // Ce qui etait vise : le nom de la route limitee, ou l'adresse du compte verrouille.
            $table->string('subject')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['type', 'created_at']);
        });

        Schema::create('audit_chain_checks', function (Blueprint $table) {
            $table->id();
            // `central`, ou `tenant:{id}` : une ligne par journal, reecrite a chaque verification.
            $table->string('scope')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('organisation')->nullable();
            $table->timestamp('checked_at');
            // Nul quand la chaine est intacte.
            $table->unsignedBigInteger('broken_entry_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_chain_checks');
        Schema::dropIfExists('security_events');
    }
};
