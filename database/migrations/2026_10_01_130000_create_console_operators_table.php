<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'equipe editeur (README section 3 et ecran 34), dans la base centrale. Une ligne par adresse
     * invitee : c'est l'adresse verifiee du compte qui ouvre la console, pas un compte a part. Tant
     * qu'aucun compte ne porte cette adresse, la ligne est une invitation en attente.
     *
     * Les Fondateurs de depart restent dans `convive.console.operators` : sans eux, personne ne
     * pourrait inviter le premier membre.
     */
    public function up(): void
    {
        Schema::create('console_operators', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('profile');
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('console_operators');
    }
};
