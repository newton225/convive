<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'acces du support (README section 3 et ecran 25), dans la base centrale : il se lit a
     * travers les organisations (la console liste les acces ouverts a un compte editeur) et se
     * verifie avant meme que la base d'une organisation ne soit ouverte.
     *
     * Un acces n'a pas de statut stocke : il est en cours tant qu'il n'est ni revoque ni arrive
     * a echeance. `support_access_views` garde chaque page consultee, en ecriture seule.
     */
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'revoked_at', 'expires_at']);
            $table->index(['operator_id', 'revoked_at', 'expires_at']);
        });

        Schema::create('support_access_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_access_grant_id')->constrained()->cascadeOnDelete();
            $table->string('page', 40);
            $table->string('route')->nullable();
            $table->timestamp('viewed_at');

            $table->index(['support_access_grant_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_access_views');
        Schema::dropIfExists('support_access_grants');
    }
};
