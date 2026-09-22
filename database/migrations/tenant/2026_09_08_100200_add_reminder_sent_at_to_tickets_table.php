<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rappel J moins 3 heures aux billets valides (README 2.7), etape 8 de « Ordre de
     * construction ». Voir la migration compagne sur `registrations` pour les rappels de preuve
     * manquante.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
