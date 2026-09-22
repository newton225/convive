<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Etape 4 de « Ordre de construction » (CLAUDE.md) : le formulaire d'inscription, ses
     * accompagnateurs, l'unite de chacun, le montant du. La reservation (`HELD`, decompte,
     * disponibilite, priorite, purge) est l'etape 5, volontairement separee : aucune colonne
     * de decompte ici, elle arrivera avec la logique qui la fait respecter.
     *
     * Table de la base d'un locataire (voir CLAUDE.md, « Multi-locataire ») : pas de colonne
     * `tenant_id`, la base elle-meme est la frontiere.
     */
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');

            $table->string('name');
            $table->string('phone');
            // Une unite desactivee reste referencable par une inscription deja prise (voir
            // CLAUDE.md, « Unites ») : cle etrangere simple, jamais de suppression en cascade.
            $table->foreignId('unit_id')->constrained();

            // Francs CFA sans decimale (README 2.5) : `tarif_par_personne x (1 + accompagnateurs)`,
            // fige au moment de l'inscription plutot que recalcule depuis le tarif courant de
            // l'evenement, qui peut changer apres coup.
            $table->unsignedInteger('amount_due');

            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('registration_companions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('unit_id')->constrained();
            $table->unsignedTinyInteger('position')->default(0);

            $table->timestamps();

            $table->index('registration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_companions');
        Schema::dropIfExists('registrations');
    }
};
