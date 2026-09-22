<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le premier passage accepte d'un billet (README 2.8), etape 7. `ticket_id` unique porte la
     * meme garantie que `registration_table_assignments.registration_id` (CLAUDE.md, « Base de
     * donnees ») : `lockForUpdate()` ne protege rien sous SQLite, c'est cette contrainte reelle
     * qui empeche deux scans concurrents d'accepter tous les deux le meme billet.
     *
     * Distincte de `scan_events`, qui journalise chaque tentative (acceptee, deja scannee,
     * refusee, forcee) : cette table ne porte que le fait « premier passage », lu pour composer
     * le message « deja scanne, a telle heure, par tel agent ».
     *
     * `performed_by_user_id` ne porte pas de cle etrangere : `User` vit dans la base centrale
     * (CLAUDE.md, « Multi-locataire »), une autre base que celle-ci. Meme choix deja fait pour
     * `causer_id` dans `activity_log`.
     */
    public function up(): void
    {
        Schema::create('ticket_arrivals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('performed_by_user_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_arrivals');
    }
};
