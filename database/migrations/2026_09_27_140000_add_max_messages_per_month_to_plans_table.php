<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plafond mensuel des messages envoyes aux invites (SECURITY.md H5). Vide, donc illimite, sur tous
 * les plans : decision du proprietaire du 2026-09-27, la mecanique est prete pour le jour ou un
 * plafond sera fixe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_messages_per_month')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_messages_per_month');
        });
    }
};
