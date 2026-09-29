<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'invite ne declare plus de montant, il peut laisser une precision sur son paiement (decision
 * du proprietaire du projet, 2026-09-29, suppression de la colonne demandee explicitement). Un
 * montant tape par l'invite ne prouvait rien : le releve se compare desormais au montant du de
 * l'inscription, et la somme reellement envoyee se lit sur la capture du recu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->text('guest_note')->nullable();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropColumn('amount_declared');
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->unsignedInteger('amount_declared')->default(0);
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropColumn('guest_note');
        });
    }
};
