<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le journal des notifications entrantes du fournisseur de paiement (`spatie/laravel-webhook-client`,
     * via `spatie/laravel-stripe-webhooks`), etape 10 de « Ordre de construction ».
     *
     * Table de la base centrale : un abonnement est central, et la notification arrive sur le
     * domaine central, sans organisation active.
     */
    public function up(): void
    {
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');
            $table->string('url', 512);
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->json('attachments')->nullable();
            $table->text('exception')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_calls');
    }
};
