<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestre les donnees de demonstration. Idempotent : le rejouer ne duplique rien.
 *
 * Les seeders d'unites, de plans, d'evenements, d'inscriptions et de preuves decrits dans
 * CLAUDE.md arriveront avec leurs modeles, aux etapes 3 a 6.
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
        ]);
    }
}
