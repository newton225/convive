<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les plans (README section 3), etape 10 de « Ordre de construction ». Table de la base
     * centrale : un plan est commun a toutes les organisations.
     *
     * Un plafond `null` veut dire illimite. Les valeurs de depart viennent de
     * `App\Enums\PlanCode::definition()`.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('monthly_price')->nullable();

            $table->unsignedInteger('max_active_events')->nullable();
            $table->unsignedInteger('max_registrations')->nullable();
            $table->unsignedInteger('max_members')->nullable();

            $table->boolean('has_reconciliation')->default(false);
            $table->boolean('has_reports')->default(false);
            $table->boolean('has_custom_domain')->default(false);
            $table->boolean('has_sso')->default(false);

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
