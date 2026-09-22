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
            $table->string('description')->nullable()->after('name');

            // Le profil Proprietaire est systeme : ni modifiable, ni supprimable, et un
            // locataire en conserve toujours au moins un actif.
            $table->boolean('is_system')->default(false)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_system']);
        });
    }
};
