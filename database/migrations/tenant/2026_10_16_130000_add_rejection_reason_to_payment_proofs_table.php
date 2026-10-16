<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le motif d'un rejet de preuve : un code du catalogue `ProofRejectionReason` et, pour « autre », une
 * precision. Colonnes facultatives : les rejets deja prononces n'ont pas de motif, et la page de
 * l'invite ne l'affiche alors pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('rejection_reason', 32)->nullable();
            $table->string('rejection_note', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'rejection_note']);
        });
    }
};
