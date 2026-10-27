<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La trace anonyme d'une preuve rejetee dont le dossier a ete purge (decision du 2026-10-27) : la
 * purge efface le dossier, la capture, le nom et le numero, mais garde de quoi reconnaitre la meme
 * reference ou la meme capture si elle est redeposee. Aucune donnee personnelle, aucun lien vers un
 * dossier ; efface avec l'evenement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rejected_proof_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->string('reference_key')->nullable();
            $table->string('perceptual_hash', 16)->nullable();
            $table->string('rejection_reason', 32)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('reference_key');
            $table->index('perceptual_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rejected_proof_traces');
    }
};
