<?php

namespace App\Support;

use Stancl\Tenancy\DatabaseConfig;

/**
 * La table `tenants` porte les colonnes `tenancy_db_username` et `tenancy_db_password`, laissees vides :
 * l'organisation se connecte avec les identifiants de la connexion centrale. Le paquet recopie pourtant
 * chaque colonne `tenancy_db_*` sur la connexion de l'organisation, valeur nulle comprise, ce qui effacerait
 * l'utilisateur et le mot de passe de PostgreSQL (« no password supplied »). SQLite n'en a pas l'usage, d'ou
 * un defaut passe inapercu jusque-la. Une colonne vide laisse donc la valeur de la connexion centrale.
 */
class TenantDatabaseConfig extends DatabaseConfig
{
    /**
     * @return array<string, mixed>
     */
    public function tenantConfig(): array
    {
        return array_filter(parent::tenantConfig(), fn (mixed $value): bool => $value !== null);
    }
}
