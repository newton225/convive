<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * « Aucune » devient une unite protegee (decision du proprietaire du projet, 2026-10-07) :
     * toujours en derniere position, ni renommee, ni desactivee, ni supprimee. Elle se reconnait a
     * cette colonne, pas a son nom, pour que la protection ne tienne pas a une chaine.
     *
     * Une organisation qui l'avait desactivee la retrouve active ; une organisation qui l'avait
     * supprimee ou renommee la retrouve en fin de liste (une unite renommee ne se reconnait plus :
     * elle reste une unite ordinaire).
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('is_none')->default(false);
        });

        $updated = DB::table('units')
            ->where('name', Unit::None)
            ->update(['is_none' => true, 'is_active' => true]);

        // Une base vide est celle d'une organisation en cours d'ouverture : `CreateStarterUnits`
        // va y creer « Aucune » juste apres les migrations.
        if ($updated === 0 && DB::table('units')->exists()) {
            DB::table('units')->insert([
                'name' => Unit::None,
                'position' => (int) DB::table('units')->max('position') + 1,
                'is_active' => true,
                'is_none' => true,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('is_none');
        });
    }
};
