<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Facultatif : demande a l'inscription du compte, jamais exige. Sans numero, l'alerte
     * WhatsApp d'un changement de compte de versement (CLAUDE.md, « Comptes de versement »)
     * n'a simplement rien ou l'envoyer, le mail reste le canal garanti.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
