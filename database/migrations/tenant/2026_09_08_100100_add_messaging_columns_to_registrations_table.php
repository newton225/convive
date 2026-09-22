<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * README 2.7, etape 8 de « Ordre de construction ». Chaque colonne marque un envoi
     * effectivement fait, jamais planifie : c'est ce qui rend chaque tache planifiee idempotente
     * (rejouee toutes les cinq minutes, elle ne doit reenvoyer ni la carte ni un rappel deja
     * parti), meme principe que `public_token` ou `held_until` ailleurs sur ce modele.
     *
     * Les rappels de preuve manquante (J-7, J-2, J-1) vivent sur `registrations` : ils ne
     * s'adressent qu'aux inscriptions pas encore confirmees. Le rappel J-3h des billets valides
     * vit sur `tickets` (voir la migration compagne), une inscription confirmee sans billet
     * n'ayant rien a rappeler.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('card_sent_at')->nullable()->after('amount_due');
            $table->timestamp('proof_reminder_j7_sent_at')->nullable()->after('card_sent_at');
            $table->timestamp('proof_reminder_j2_sent_at')->nullable()->after('proof_reminder_j7_sent_at');
            $table->timestamp('proof_reminder_j1_sent_at')->nullable()->after('proof_reminder_j2_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn([
                'card_sent_at',
                'proof_reminder_j7_sent_at',
                'proof_reminder_j2_sent_at',
                'proof_reminder_j1_sent_at',
            ]);
        });
    }
};
