<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * L'identite legale et la marque vivent a part de `tenants` : elles sont optionnelles,
     * volumineuses, et ne sont lues que sur le formulaire d'organisation, les billets et les
     * recus. La table `tenants` reste ce qui est charge a chaque requete.
     */
    public function up(): void
    {
        Schema::create('tenant_brandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('display_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('representative_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('primary_color', 32)->nullable();
            $table->string('secondary_color', 32)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_brandings');
    }
};
