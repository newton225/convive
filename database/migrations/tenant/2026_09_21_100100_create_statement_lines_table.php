<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les lignes d'un releve importe et leur issue de rapprochement (README 2.10), etape 9.
     *
     * L'issue vit directement sur la ligne : pas de table de correspondances tant qu'aucun besoin
     * many-to-many reel n'existe (une ligne designe au plus une inscription et une preuve).
     *
     * Les deux cles de rapprochement sont `nullOnDelete` : la purge d'une inscription (README
     * 2.4) supprime ses preuves en cascade, elle ne doit pas emporter une ligne de releve, qui
     * est la trace d'un versement reel.
     *
     * `resolved_at` distingue « pas encore regardee » de « vue, sans correspondance ».
     * `resolved_by_user_id` sans cle etrangere : `User` vit dans la base centrale.
     */
    public function up(): void
    {
        Schema::create('statement_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('statement_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->date('occurred_on');
            $table->string('reference')->nullable();
            $table->string('issuer');
            $table->unsignedInteger('amount');
            $table->string('outcome');

            $table->foreignId('matched_registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('matched_payment_proof_id')->nullable()->constrained('payment_proofs')->nullOnDelete();

            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['statement_import_id', 'line_number']);
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statement_lines');
    }
};
