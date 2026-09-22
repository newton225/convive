<?php

namespace Tests;

use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ScopedSqliteDatabaseManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Une base par locataire (voir CLAUDE.md, « Multi-locataire ») est un vrai fichier
     * SQLite, jamais couvert par `RefreshDatabase` : seule la connexion centrale est en
     * memoire. Avec des identifiants entiers auto-incrementes, chaque test recree un locataire
     * d'identifiant 1 des la premiere organisation : sans ce nettoyage, le second test de la
     * suite trouverait le fichier du premier deja present et sa creation echouerait.
     *
     * `ScopedSqliteDatabaseManager` range ces fichiers sous `storage/framework/testing/`,
     * jamais dans `database/` : ce nettoyage ne peut donc plus effacer la base d'un locataire
     * reel d'un developpeur qui aurait `php artisan serve` ouvert a cote (voir la classe pour
     * l'incident que cet ecart a cause avant sa correction).
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Sous Windows, un fichier SQLite reste verrouille tant que le PDO du test precedent
        // n'a pas ete reellement libere : `purgeTenantConnection()` (stancl/tenancy) le
        // dereference, mais sa destruction depend du ramasse-miettes de PHP, pas immediate par
        // defaut. `gc_collect_cycles()` aide, mais reste une course sur une suite complete (des
        // centaines de bases creees et refermees dans le meme processus) : un `unlink()` isole
        // echoue encore de temps en temps, silencieusement (l'erreur est etouffee par `@`), et
        // le prochain locataire d'identifiant 1 se heurte a un fichier que le test precedent
        // croyait avoir efface. Une poignee de tentatives, espacees d'une pause tres courte,
        // couvre ce dernier ecart sans ralentir la suite dans le cas courant ou le fichier
        // s'efface du premier coup.
        gc_collect_cycles();

        foreach (glob(ScopedSqliteDatabaseManager::directory().'/tenant*.sqlite') ?: [] as $path) {
            for ($attempt = 0; $attempt < 5 && file_exists($path); $attempt++) {
                if ($attempt > 0) {
                    usleep(20_000);
                    gc_collect_cycles();
                }

                @unlink($path);
            }
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Add the user to the tenant carrying its system owner profile.
     *
     * Le profil vit dans la base du locataire : la lecture passe par `$tenant->run()`.
     */
    protected function joinAsOwner(Tenant $tenant, User $user): Profile
    {
        $profile = $tenant->run(fn () => Profile::where('name', Profile::Owner)->firstOrFail());

        $tenant->addMember($user, $profile);

        return $profile;
    }

    /**
     * Add the user to the tenant carrying one of its starter profiles.
     */
    protected function joinWithProfile(Tenant $tenant, User $user, string $profileName): Profile
    {
        $profile = $tenant->run(fn () => Profile::where('name', $profileName)->firstOrFail());

        $tenant->addMember($user, $profile);

        return $profile;
    }

    /**
     * Add the user to the tenant carrying a profile built for the given permissions.
     *
     * @param  array<int, TenantPermission>  $permissions
     */
    protected function joinWithPermissions(Tenant $tenant, User $user, array $permissions, string $name = 'Personnalise'): Profile
    {
        $profile = $tenant->run(function () use ($name, $permissions) {
            $profile = Profile::create([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            $profile->syncPermissions(
                array_map(fn (TenantPermission $permission) => $permission->value, $permissions),
            );

            return $profile;
        });

        $tenant->addMember($user, $profile);

        return $profile;
    }
}
