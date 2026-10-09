<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Les reclamations des invites (decision du proprietaire du projet, 2026-10-09) : un message
     * sur un dossier, que l'organisation lit et traite. Supprimees avec l'inscription (purge), pour
     * ne pas garder de message personnel au-dela de la vie du dossier.
     */
    public function up(): void
    {
        Schema::create('guest_claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->text('message');
            $table->string('status')->default('open');

            $table->dateTime('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();

            $table->timestamps();

            $table->index('registration_id');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_claims');
    }
};
