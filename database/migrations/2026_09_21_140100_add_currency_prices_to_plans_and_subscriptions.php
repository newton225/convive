<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'euro et le dollar s'ajoutent au franc CFA (etape 10). `monthly_price` reste le prix en
     * francs CFA ; les nouvelles colonnes portent le prix mensuel en centimes. `null` veut dire
     * que le plan n'est pas propose dans cette devise.
     *
     * `subscriptions.currency` garde la devise choisie au paiement, pour que les factures et
     * l'affichage restent coherents meme si la liste des devises proposees change plus tard.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('monthly_price_eur')->nullable()->after('monthly_price');
            $table->unsignedInteger('monthly_price_usd')->nullable()->after('monthly_price_eur');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_price_eur', 'monthly_price_usd']);
        });
    }
};
