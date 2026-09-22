<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * README 2.3 : quand l'evenement est complet, l'invite rejoint une liste d'attente
     * ordonnee. Le rang n'est pas stocke (voir `App\Models\WaitlistEntry::position()`) : il se
     * calcule par ordre d'arrivee parmi ceux encore `Waiting`, pour ne jamais avoir a
     * renumeroter la file quand quelqu'un la quitte.
     *
     * `companions` est un JSON, pas une table a part comme `registration_companions` : tant
     * que l'inscription n'est pas convertie (voir `App\Actions\Waitlist\FinalizeWaitlistEntry`),
     * ces donnees ne sont qu'un brouillon en salle d'attente, pas encore un enregistrement de
     * premier ordre a mettre en relation.
     *
     * Table de la base d'un locataire (voir CLAUDE.md, « Multi-locataire ») : pas de colonne
     * `tenant_id`, la base elle-meme est la frontiere.
     */
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('waiting');

            $table->string('name');
            $table->string('phone');
            $table->foreignId('unit_id')->constrained();
            $table->unsignedTinyInteger('party_size')->default(1);
            $table->json('companions');

            $table->string('resume_token_hash', 64)->unique();

            // Poses a la promotion (README 2.3) : le lien de finalisation est valable six
            // heures, passe ce delai on invite le suivant.
            $table->dateTime('invited_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
