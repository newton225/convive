<?php

namespace App\Support;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;
use Throwable;

/**
 * Range les fichiers SQLite des locataires crees pendant les tests a l'ecart de ceux du
 * developpement local, tous deux nommes de la meme facon (`tenant{id}.sqlite`, voir
 * `config/tenancy.php`).
 *
 * `Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager` les place tous dans
 * `database_path()`, sans distinction d'environnement. La base centrale de test tourne en
 * memoire (`DB_CENTRAL_DATABASE=:memory:`, voir `phpunit.xml`) et recommence a l'identifiant 1
 * a chaque test, alors qu'un environnement de developpement local persiste les siens sur
 * disque : les deux se disputaient donc le meme fichier `tenant1.sqlite` ou `tenant2.sqlite`,
 * et le nettoyage entre deux tests (`Tests\TestCase::setUp()`) effacait au passage la base
 * tenant reelle d'un developpeur qui aurait lance la suite en parallele de `php artisan serve`.
 * Ce fut le cas une fois, avec perte des donnees de demonstration du locataire concerne.
 */
class ScopedSqliteDatabaseManager extends SQLiteDatabaseManager
{
    /**
     * Get the directory tenant databases are stored under for the current environment.
     */
    public static function directory(): string
    {
        return app()->environment('testing')
            ? storage_path('framework/testing/tenant-databases')
            : database_path();
    }

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $directory = static::directory();

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        try {
            return file_put_contents($directory.DIRECTORY_SEPARATOR.$tenant->database()->getName(), '') !== false;
        } catch (Throwable $th) {
            return false;
        }
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            return unlink(static::directory().DIRECTORY_SEPARATOR.$tenant->database()->getName());
        } catch (Throwable $th) {
            return false;
        }
    }

    public function databaseExists(string $name): bool
    {
        return file_exists(static::directory().DIRECTORY_SEPARATOR.$name);
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['database'] = static::directory().DIRECTORY_SEPARATOR.$databaseName;

        return $baseConfig;
    }
}
