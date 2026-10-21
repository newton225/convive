<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L'unite protegee « Aucune » devient « Invité spécial » (decision du proprietaire du projet,
 * 2026-10-21). Elle se reconnait a `is_none`, jamais a son nom : seule cette colonne designe la ligne.
 *
 * Une unite ordinaire au nom voisin (« Invité special », « invité spécial »...) ferait deux lignes
 * identiques a l'ecran : elle est desactivee et marquee « (ancienne) », sans perdre les inscriptions
 * qui la portent. La comparaison ignore accents et casse, comme la recherche de l'application.
 */
return new class extends Migration
{
    public function up(): void
    {
        $normalize = fn (string $name): string => Str::lower(Str::ascii($name));

        DB::table('units')
            ->where('is_none', false)
            ->get(['id', 'name'])
            ->filter(fn (object $unit) => $normalize($unit->name) === $normalize(Unit::None))
            ->each(fn (object $unit) => DB::table('units')->where('id', $unit->id)->update([
                'name' => $unit->name.' (ancienne)',
                'is_active' => false,
            ]));

        DB::table('units')->where('is_none', true)->update(['name' => Unit::None]);
    }

    public function down(): void
    {
        DB::table('units')->where('is_none', true)->where('name', Unit::None)->update(['name' => 'Aucune']);
    }
};
