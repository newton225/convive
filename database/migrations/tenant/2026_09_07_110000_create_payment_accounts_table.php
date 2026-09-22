<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Les colonnes vivantes (`channel`, `account_number`, `holder_name`) sont celles que voit
     * l'invite. Les colonnes `pending_*` portent une modification demandee mais pas encore
     * active : c'est ce qui permet de garder l'ancien numero affiche pendant le delai
     * d'activation, comme l'exige SECURITY.md C1.
     *
     * Table de la base d'un locataire (voir CLAUDE.md, « Multi-locataire ») : pas de colonne
     * `tenant_id`. `pending_requested_by` et `pending_approved_by` referencent `users`, qui
     * reste dans la base centrale : aucune contrainte de cle etrangere n'est possible entre
     * deux bases separees, la relation Eloquent continue de fonctionner (chaque cote resout sa
     * propre connexion), seule la contrainte SQL disparait.
     */
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('label');
            $table->string('channel')->nullable();
            $table->string('account_number')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('instructions')->nullable();

            $table->string('pending_channel')->nullable();
            $table->string('pending_account_number')->nullable();
            $table->string('pending_holder_name')->nullable();
            $table->timestamp('pending_activates_at')->nullable();
            $table->unsignedBigInteger('pending_requested_by')->nullable();
            $table->unsignedBigInteger('pending_approved_by')->nullable();

            $table->timestamp('last_changed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
            $table->index('pending_activates_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
