<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Un profil peut exiger la double authentification de ses porteurs : le
            // back-office leur reste ferme tant qu'ils ne l'ont pas activee.
            $table->boolean('requires_two_factor')->default(false)->after('is_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('requires_two_factor');
        });
    }
};
