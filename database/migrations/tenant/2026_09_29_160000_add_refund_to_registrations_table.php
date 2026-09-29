<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sort du paiement d'une inscription validee puis annulee (README 2.11). Le montant rembourse
 * n'est pas stocke : c'est le montant paye (`amount_due`, fige a la validation) moins les frais,
 * rembourser se faisant en tout ou rien. Les frais sont ceux que l'operateur a preleves, saisis
 * tels quels, jamais deduits d'un taux.
 *
 * `refund_recorded_by_user_id` sans cle etrangere : l'auteur vit dans la base centrale, comme
 * `cancelled_by_user_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('refund_status', 16)->nullable()->index();
            $table->string('refund_channel', 32)->nullable();
            $table->date('refunded_on')->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->unsignedInteger('refund_fee')->nullable();
            $table->string('refund_kept_reason', 500)->nullable();
            $table->timestamp('refund_recorded_at')->nullable();
            $table->unsignedBigInteger('refund_recorded_by_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn([
                'refund_status', 'refund_channel', 'refunded_on', 'refund_reference',
                'refund_fee', 'refund_kept_reason', 'refund_recorded_at', 'refund_recorded_by_user_id',
            ]);
        });
    }
};
