<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'historique d'un dossier : les faits qui n'ont pas d'auteur connecte (l'invite qui reserve, depose
 * une preuve ou entre en liste d'attente, le systeme qui fait expirer un decompte, attribue une table
 * ou emet les billets). Les gestes de l'equipe restent dans le journal d'audit.
 *
 * Ecriture seule. Les lignes suivent le dossier : effacer l'inscription ou l'evenement les efface.
 * `registration_id` est nul tant qu'une personne n'est qu'en liste d'attente. L'adresse IP ne se
 * garde que douze mois (`registration_events:prune`), le reste suit le dossier. Aucun nom ni numero
 * de telephone ici : le dossier les porte deja, la reference suffit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('waitlist_entry_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('actor');
            $table->string('ip_address', 45)->nullable();
            $table->json('properties')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['registration_id', 'created_at']);
            $table->index(['waitlist_entry_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_events');
    }
};
