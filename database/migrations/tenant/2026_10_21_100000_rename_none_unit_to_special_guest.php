<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'unite protegee « Aucune » devient « Invité spécial » (decision du proprietaire du projet,
 * 2026-10-21). Elle se reconnait a `is_none`, jamais a son nom : seule cette colonne designe la ligne.
 * Une organisation qui avait deja une autre unite de ce nom garde l'ancien : le nom est unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = DB::table('units')->where('name', Unit::None)->where('is_none', false)->exists();

        if (! $taken) {
            DB::table('units')->where('is_none', true)->update(['name' => Unit::None]);
        }
    }

    public function down(): void
    {
        DB::table('units')->where('is_none', true)->where('name', Unit::None)->update(['name' => 'Aucune']);
    }
};
