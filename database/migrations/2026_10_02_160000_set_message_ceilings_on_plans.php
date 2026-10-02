<?php

use App\Enums\PlanCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plafond mensuel de messages aux invites (SECURITY.md H5), arrete par le proprietaire du projet le
 * 2026-10-02 : 1 000 pour Essentiel, 5 000 pour Association, aucun pour Institution.
 *
 * `Plan::ensure()` ne touche plus une ligne deja creee : les plans existants recoivent donc leur
 * plafond ici. Seules les lignes encore sans plafond sont concernees, pour ne pas ecraser un
 * reglage fait depuis la console.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([PlanCode::Essential, PlanCode::Association] as $code) {
            DB::table('plans')
                ->where('code', $code->value)
                ->whereNull('max_messages_per_month')
                ->update(['max_messages_per_month' => $code->definition()['max_messages_per_month']]);
        }
    }

    public function down(): void
    {
        DB::table('plans')->update(['max_messages_per_month' => null]);
    }
};
