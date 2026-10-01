<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La demande d'aide (README section 3) : quand personne de l'equipe Convive n'est visible, un
     * Proprietaire signale qu'il veut ouvrir son espace, et dit pourquoi. Base centrale, comme les
     * acces : la console la lit a travers les organisations.
     *
     * Une demande n'a pas de statut stocke : elle est en attente tant que `closed_at` est vide,
     * prise en charge des que `taken_at` est pose.
     */
    public function up(): void
    {
        Schema::create('support_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->foreignId('taken_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'closed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_access_requests');
    }
};
