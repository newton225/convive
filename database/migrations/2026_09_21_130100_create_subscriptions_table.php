<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'abonnement d'une organisation (README section 3, « Facturation »), etape 10.
     *
     * Une seule ligne par organisation (`tenant_id` unique) : une organisation sans ligne vit sur
     * le plan par defaut (`PlanCode::default()`). Les colonnes `stripe_*` et `payment_method_*`
     * ne sont renseignees que par le fournisseur de paiement.
     *
     * Chaque etape de la relance porte sa propre colonne (`past_due_since`,
     * `overdue_reminder_sent_at`, `suspended_at`) plutot que de se deduire d'une date de
     * reference : une tache planifiee rejouee ne doit ni renvoyer la relance ni suspendre deux
     * fois (meme principe que `registrations.card_sent_at`).
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status');

            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('payment_method_brand')->nullable();
            $table->string('payment_method_last4', 4)->nullable();

            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('past_due_since')->nullable();
            $table->timestamp('overdue_reminder_sent_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'past_due_since']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
