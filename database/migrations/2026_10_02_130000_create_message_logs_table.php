<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le releve des envois (README section 3) : un courriel ou un message WhatsApp parti, son type
     * et son destinataire masque. Base centrale : les envois se lisent a travers les organisations.
     * Jamais le contenu du message, qui porte des liens signes ; purge au bout de 30 jours.
     *
     * `tenant_id` ne porte pas de contrainte : la trace survit a l'effacement d'une organisation.
     */
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('type', 100);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('recipient', 120)->nullable();
            // Vrai quand le canal n'envoie pas reellement (journal a la place d'un prestataire).
            $table->boolean('simulated')->default(false);
            $table->timestamp('created_at');

            $table->index(['channel', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
