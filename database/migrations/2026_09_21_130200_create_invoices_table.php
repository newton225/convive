<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'historique des factures d'abonnement (README section 3), etape 10.
     *
     * Montants en francs CFA sans decimale : un entier est la representation exacte (CLAUDE.md).
     * `stripe_invoice_id` unique porte l'idempotence des notifications du fournisseur : rejouer
     * une meme facture ne cree pas de doublon.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');
            $table->string('status');

            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('hosted_invoice_url', 2048)->nullable();

            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
