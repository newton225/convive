<?php

namespace App\Support\Console;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

/**
 * L'etat de la base de chaque organisation (README ecran 31) : absente, ou en retard de migrations.
 * Un locataire sans ses migrations est un locataire casse (CLAUDE.md, « Multi-locataire ») : la
 * console le montre, et `RepairTenantDatabase` le repare.
 *
 * Le releve ouvre chaque base : il est garde quelques minutes en cache plutot que refait a chaque
 * affichage, et oublie des qu'une reparation a tourne.
 */
class TenantDatabaseHealth
{
    private const CacheKey = 'console:tenant-database-health';

    private const CacheMinutes = 5;

    /**
     * Get the organisations whose database is missing or behind on migrations.
     *
     * @return array<int, array{slug: string, name: string, issue: string, pendingMigrations: int|null}>
     */
    public static function issues(): array
    {
        return Cache::remember(self::CacheKey, now()->addMinutes(self::CacheMinutes), fn () => Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => self::of($tenant))
            ->filter()
            ->values()
            ->all());
    }

    /**
     * Get what is wrong with the database of the given organisation, or null when it is sound.
     *
     * @return array{slug: string, name: string, issue: string, pendingMigrations: int|null}|null
     */
    public static function of(Tenant $tenant): ?array
    {
        $missing = [
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'issue' => 'missing_database',
            'pendingMigrations' => null,
        ];

        if (! $tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
            return $missing;
        }

        try {
            $ran = $tenant->run(fn () => Schema::hasTable('migrations')
                ? DB::table('migrations')->pluck('migration')->all()
                : []);
        } catch (TenantDatabaseDoesNotExistException) {
            // L'echec survient dans `initialize()`, avant le `finally` de `Tenant::run()`.
            tenancy()->end();

            return $missing;
        }

        $pending = count(array_diff(self::tenantMigrations(), $ran));

        return $pending === 0 ? null : [
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'issue' => 'pending_migrations',
            'pendingMigrations' => $pending,
        ];
    }

    public static function forget(): void
    {
        Cache::forget(self::CacheKey);
    }

    /**
     * Get the names of the migrations every organisation database must have run.
     *
     * @return array<int, string>
     */
    private static function tenantMigrations(): array
    {
        return array_keys(app('migrator')->getMigrationFiles(database_path('migrations/tenant')));
    }
}
