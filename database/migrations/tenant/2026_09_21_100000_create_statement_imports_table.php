<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les imports de releve Mobile Money ou bancaire (README 2.10, ecran 19), etape 9 de « Ordre
     * de construction ».
     *
     * `(event_id, content_hash)` unique porte l'idempotence : reimporter le meme fichier pour le
     * meme evenement est refuse par une contrainte reelle, pas seulement par un controle
     * applicatif (`lockForUpdate()` ne protege rien sous SQLite, voir CLAUDE.md, « Base de
     * donnees »). Le meme contenu reste importable sur un autre evenement.
     *
     * `imported_by_user_id` ne porte pas de cle etrangere : `User` vit dans la base centrale,
     * une autre base physique que celle-ci.
     *
     * Table de la base d'un locataire : pas de colonne `tenant_id`.
     */
    public function up(): void
    {
        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('imported_by_user_id');
            $table->string('original_filename');
            $table->string('content_hash', 64);
            $table->unsignedInteger('row_count');

            $table->timestamps();

            $table->unique(['event_id', 'content_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statement_imports');
    }
};
