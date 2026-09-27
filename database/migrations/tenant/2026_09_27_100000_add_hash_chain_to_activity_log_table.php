<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chainage par empreinte du journal d'audit (SECURITY.md M6) : chaque entree porte l'empreinte de
 * la precedente, une suppression ou une modification hors de la purge tracee devient detectable.
 * Colonnes nullables : les entrees anterieures a cette migration restent en l'etat, la chaine
 * commence a la premiere entree ecrite ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropColumn(['previous_hash', 'hash']);
        });
    }
};
