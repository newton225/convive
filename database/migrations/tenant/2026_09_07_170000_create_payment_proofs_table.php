<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le depot de preuve (README 2.1, 2.9, ecran 6), etape 6 de « Ordre de construction ».
     *
     * Une inscription peut porter plusieurs preuves dans le temps : un rejet (README 2.1,
     * `PROOF_REJECTED` puis retour a `HELD`) autorise une nouvelle soumission, avec sa propre
     * capture et sa propre reference. Pas de `hasOne`, donc, et pas de suppression en cascade
     * a l'echelle de la ligne : chaque preuve reste tracee, y compris celles qui ont ete
     * rejetees.
     *
     * Table de la base d'un locataire (voir CLAUDE.md, « Multi-locataire ») : pas de colonne
     * `tenant_id`, la base elle-meme est la frontiere.
     */
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained();

            $table->string('channel');
            // Nulle pour un versement en especes (README, `PaymentChannel::hasAccountNumber()`) :
            // aucune reference de transaction n'existe alors a declarer.
            $table->string('reference')->nullable();
            $table->unsignedInteger('amount_declared');

            // Empreinte perceptuelle du recu (README 2.9), calculee a la reception : sert a
            // repérer une capture deja vue sur une autre inscription. Indexee pour la recherche
            // de doublon, jamais recalculee a la lecture.
            $table->string('perceptual_hash', 16)->nullable();

            // Cle d'idempotence fournie par le client (CLAUDE.md, « Securite ») : un double clic
            // ou un rejeu reseau sur la soumission ne doit pas creer deux preuves.
            $table->string('idempotency_key', 64)->unique();

            $table->timestamps();

            $table->index('registration_id');
            $table->index('reference');
            $table->index('perceptual_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
