<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestre les donnees de demonstration. Idempotent : le rejouer ne duplique rien.
 *
 * Pas de seeder d'unites separe : `TenantSeeder` cree l'organisation via `CreateTenant`, qui
 * seme deja ses unites de depart (`App\Actions\Tenants\CreateStarterUnits`), comme a l'ouverture
 * de n'importe quel espace (CLAUDE.md, « Unites »). Un `UnitSeeder` a part dupliquerait ce que
 * l'application fait deja toute seule.
 *
 * Pas de `WithoutModelEvents` ici : les modeles du projet s'appuient sur leurs evenements
 * Eloquent, notamment `Tenant::creating` qui fabrique le slug. Les faire taire pendant le
 * semis produirait des enregistrements que l'application ne cree jamais.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Le compte de developpement et son mot de passe connu n'ont rien a faire ailleurs
        // qu'en local et en test.
        if (app()->isProduction()) {
            $this->command->warn('Les donnees de demonstration ne sont pas semees en production.');

            return;
        }

        $this->call([
            PlanSeeder::class,
            TenantSeeder::class,
            TeamSeeder::class,
            EventSeeder::class,
            RegistrationSeeder::class,
            PaymentProofSeeder::class,
        ]);
    }
}
