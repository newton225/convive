<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empreinte du code de scan a 4 chiffres (SECURITY.md M8) : sel, iterations et resultat PBKDF2.
 * Jamais le code lui-meme. L'appareil de l'agent recoit cette empreinte pour deverrouiller l'ecran
 * de scan meme sans reseau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('scan_pin_verifier')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scan_pin_verifier');
        });
    }
};
