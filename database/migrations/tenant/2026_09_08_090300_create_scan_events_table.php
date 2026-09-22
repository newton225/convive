<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le journal des passages a l'entree (README 2.8, ecran 26), etape 7 de « Ordre de
     * construction ». Ecriture seule : chaque tentative de scan (acceptee, deja scannee,
     * refusee), forcee ou non, y ecrit une ligne, jamais une mise a jour.
     *
     * `ticket_id` est nullable : un jeton dont la signature ne verifie pas, expire, ou d'un
     * autre evenement ne resout aucun billet reel, mais la tentative refusee reste journalisee
     * quand meme (README 2.8 : « chaque scan... est journalise »).
     *
     * `event_id` est repete ici bien que `ticket_id` y mene deja indirectement : necessaire
     * pour scoper le journal d'un evenement quand `ticket_id` est justement absent.
     *
     * `performed_by_user_id` sans cle etrangere : meme raison que sur `ticket_arrivals`,
     * `User` vit dans la base centrale.
     */
    public function up(): void
    {
        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('performed_by_user_id');
            $table->string('result');
            $table->boolean('forced')->default(false);

            $table->timestamps();

            $table->index(['event_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_events');
    }
};
