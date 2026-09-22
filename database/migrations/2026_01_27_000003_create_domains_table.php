<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fournie par `stancl/tenancy`. `domain` porte le sous-domaine (ou le domaine complet
     * selon la strategie d'identification), pas un chemin : c'est lui que
     * `InitializeTenancyBySubdomain` compare au host de la requete pour resoudre le locataire
     * du lien public, avant meme que la connexion de base ne soit basculee.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
