<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification du telephone par code avant la reservation (SECURITY.md C3) : reglage par
 * evenement, desactive par defaut (decision du 2026-09-27), et l'etat du code sur l'inscription.
 * Le code n'est jamais stocke en clair, seulement son empreinte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('rule_phone_verification')->default(false);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('phone_code_hash')->nullable();
            $table->timestamp('phone_code_expires_at')->nullable();
            $table->unsignedTinyInteger('phone_code_attempts')->default(0);
            $table->timestamp('phone_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['phone_code_hash', 'phone_code_expires_at', 'phone_code_attempts', 'phone_verified_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('rule_phone_verification');
        });
    }
};
