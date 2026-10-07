<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le delai d'activation des comptes de versement part de la premiere publication d'un
     * evenement (decision du proprietaire du projet, 2026-10-07), et ne s'arrete plus ensuite.
     * Une date et non un booleen : elle se pose une fois, a la premiere publication, et ne se
     * retire jamais. Pour une organisation qui avait deja publie, `Tenant::paymentAccountDelayApplies()`
     * la deduit de ses evenements a la premiere lecture.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('first_published_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('first_published_at');
        });
    }
};
