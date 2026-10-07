<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La verification de l'adresse devient obligatoire (decision du proprietaire du projet,
     * 2026-10-07). Les comptes ouverts avant sont consideres verifies : personne n'est bloque a sa
     * prochaine connexion, seuls les nouveaux comptes confirment leur adresse.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()->format('Y-m-d H:i:s')]);
    }

    /**
     * Reverse the migrations.
     *
     * Rien a defaire : on ne sait plus quels comptes n'avaient pas verifie leur adresse.
     */
    public function down(): void
    {
        //
    }
};
