<?php

namespace Tests;

use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ScopedSqliteDatabaseManager;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use Stancl\Tenancy\Events\DatabaseCreated;
use Tests\Support\OutOfTransactionPostgreSQLDatabaseManager;

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
    /**
     * Les bases d'organisation creees pendant un test sur PostgreSQL, a effacer ensuite.
     *
     * @var array<int, string>
     */
    private array $createdTenantDatabases = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (self::onPostgres()) {
            $this->prepareTenantDatabasesOnPostgres();
        }

        // Aucun test n'atteint un service reel (WhatsApp, SMS...) : une requete HTTP non simulee
        // par `Http::fake()` echoue au lieu de partir. Incident du 2026-10-03 : avec de vrais
        // identifiants dans le .env, des tests ont appele Twilio pour de bon.
        Http::preventStrayRequests();

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

    protected function tearDown(): void
    {
        if (self::onPostgres()) {
            $this->dropCreatedTenantDatabases();
        }

        parent::tearDown();
    }

    /**
     * Suite lancee sur PostgreSQL (`DB_CENTRAL_DRIVER=pgsql`) plutot que sur SQLite en memoire.
     */
    private static function onPostgres(): bool
    {
        return (getenv('DB_CENTRAL_DRIVER') ?: ($_ENV['DB_CENTRAL_DRIVER'] ?? 'sqlite')) === 'pgsql';
    }

    /**
     * Garde-fou, puis creation des bases d'organisation hors de la transaction du test.
     *
     * `RefreshDatabase` enveloppe chaque test dans une transaction de la connexion centrale, or
     * PostgreSQL refuse `CREATE DATABASE` dans un bloc de transaction. Le gestionnaire de bases du paquet
     * passe donc par une seconde connexion, `central_ddl`, qui n'en porte aucune. Les bases creees sont
     * retenues puis effacees a la fin du test : le retour en arriere de la transaction ne les retire pas.
     *
     * La base centrale d'essai doit se terminer par `_test` : `migrate:fresh` de `RefreshDatabase`
     * efface tout ce qu'elle contient, jamais la base de developpement.
     */
    private function prepareTenantDatabasesOnPostgres(): void
    {
        $name = (string) DB::connection('central')->getDatabaseName();

        if (! str_ends_with($name, '_test')) {
            $this->fail("Les tests sur PostgreSQL exigent une base dont le nom se termine par _test, pas « {$name} ».");
        }

        config([
            'database.connections.central_ddl' => config('database.connections.central'),
            'tenancy.database.managers.pgsql' => OutOfTransactionPostgreSQLDatabaseManager::class,
        ]);

        $this->createdTenantDatabases = [];

        Event::listen(DatabaseCreated::class, function (DatabaseCreated $event): void {
            $this->createdTenantDatabases[] = $event->tenant->database()->getName();
        });
    }

    private function dropCreatedTenantDatabases(): void
    {
        if ($this->createdTenantDatabases === []) {
            return;
        }

        tenancy()->end();
        DB::purge('tenant');
        gc_collect_cycles();

        $central = DB::connection('central_ddl');

        foreach ($this->createdTenantDatabases as $name) {
            // FORCE (PostgreSQL 13 et plus) : une connexion encore ouverte ne bloque pas l'effacement.
            $central->statement('drop database if exists "'.$name.'" with (force)');
        }

        $this->createdTenantDatabases = [];
    }

    /**
     * Les routes sensibles (identite legale, profils, abonnement, membres, comptes de versement)
     * redemandent le mot de passe (SECURITY.md H8 et M5). Par defaut, un test agit comme un
     * membre qui vient de le confirmer, exactement comme `PaymentAccountTest::actingAsConfirmed()`.
     * Une classe qui teste cette exigence elle-meme passe ce drapeau a `false`.
     */
    protected bool $confirmsPasswordOnActingAs = true;

    public function actingAs(UserContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($this->confirmsPasswordOnActingAs) {
            // `now()` et non `time()` : apres un voyage dans le temps, une confirmation datee de
            // l'horloge reelle serait consideree comme perimee.
            $this->session(['auth.password_confirmed_at' => now()->getTimestamp()]);
        }

        return $this;
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
