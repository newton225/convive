<?php

namespace Database\Seeders;

use App\Enums\PlanCode;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Essentiel, Association, Institution avec leurs quotas (README section 3). Idempotent : ne
 * touche pas a une ligne deja presente (voir `Plan::ensure()`).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlanCode::cases() as $code) {
            Plan::ensure($code);
        }
    }
}
