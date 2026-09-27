<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Vitrine du site produit (CLAUDE.md, « Annonce sur le site produit ») : une ligne par
     * evenement annonce, base centrale. La vitrine ne boucle jamais sur les bases des
     * locataires a chaque affichage (meme risque qu'une tache planifiee non bornee, ici sur le
     * chemin critique d'une page publique) ; cette table est tenue a jour en ecriture par
     * `App\Actions\Events\SaveEvent` chaque fois que `announced_at`, le nom, la date ou le
     * visuel changent, jamais reconstruite a la volee depuis N bases.
     *
     * `event_id` n'est pas une cle etrangere : il designe une ligne dans la base du locataire
     * `tenant_id`, une connexion distincte que la base centrale ne peut pas contraindre.
     */
    public function up(): void
    {
        Schema::create('showcase_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');

            $table->string('name');
            $table->string('organisation_name');
            $table->timestamp('starts_at')->nullable();
            // Adresse complete avec jeton (CLAUDE.md) : la vitrine ne fait qu'exposer un lien
            // deja rendu public par l'organisateur, jamais un identifiant court ou sequentiel.
            $table->string('public_url');

            $table->timestamp('announced_at');

            $table->timestamps();

            $table->unique(['tenant_id', 'event_id']);
            $table->index('announced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('showcase_events');
    }
};
