<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Les parametres poses ici pilotent tout le noyau critique de l'etape 5 : disponibilite,
     * decompte de reservation, purge, montant du. Ils doivent etre justes avant qu'on ecrive
     * ce calcul, sinon on eprouve la concurrence sur des valeurs fausses.
     *
     * Table de la base d'un locataire (voir CLAUDE.md, « Multi-locataire ») : pas de colonne
     * `tenant_id`, la base elle-meme est la frontiere.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->string('status')->default('draft');

            $table->dateTime('starts_at')->nullable();
            $table->string('venue')->nullable();
            $table->string('venue_address')->nullable();

            // La capacite n'est pas saisie : elle vaut tables x places par table. Une capacite
            // stockee a part finirait par diverger du plan de salle, et c'est le plan de salle
            // qui fait foi le jour J.
            $table->unsignedSmallInteger('table_count')->default(0);
            $table->unsignedSmallInteger('seats_per_table')->default(0);

            // Montants en francs CFA, sans decimale : un entier est la representation exacte.
            $table->unsignedInteger('price_per_person')->default(0);
            $table->unsignedTinyInteger('companion_limit')->default(10);

            $table->dateTime('registration_deadline')->nullable();
            $table->dateTime('purge_at')->nullable();
            $table->dateTime('invitations_send_at')->nullable();
            $table->unsignedSmallInteger('hold_duration_minutes')->default(10);

            // Jeton du lien public : aleatoire, jamais un identifiant sequentiel devinable.
            $table->string('public_token', 64)->nullable()->unique();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('starts_at');
        });

        // Les comptes de versement retenus pour cet evenement : un evenement peut n'en
        // proposer qu'une partie. Les deux cotes vivent dans la meme base de locataire, la
        // contrainte de cle etrangere reste possible et utile ici.
        Schema::create('event_payment_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();

            $table->unique(['event_id', 'payment_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_payment_account');
        Schema::dropIfExists('events');
    }
};
