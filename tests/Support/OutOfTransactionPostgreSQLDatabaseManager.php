<?php

namespace Tests\Support;

use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;

/**
 * Le gestionnaire de bases d'organisation du paquet, sur une connexion sans transaction.
 *
 * Les tests enveloppent chaque test dans une transaction de la connexion centrale
 * (`RefreshDatabase`) ; `CREATE DATABASE` et `DROP DATABASE` y sont interdits. Cette classe les envoie
 * par `central_ddl`, une seconde connexion vers la meme base, declaree par `Tests\TestCase`.
 */
class OutOfTransactionPostgreSQLDatabaseManager extends PostgreSQLDatabaseManager
{
    public function setConnection(string $connection): void
    {
        parent::setConnection('central_ddl');
    }
}
