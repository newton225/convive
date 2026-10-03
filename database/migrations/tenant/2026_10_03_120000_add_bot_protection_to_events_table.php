<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protection anti-robot du formulaire d'inscription (Cloudflare Turnstile) : reglage par
 * evenement, active par defaut, y compris sur les evenements existants (decision du proprietaire
 * du projet, 2026-10-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('rule_bot_protection')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('rule_bot_protection');
        });
    }
};
