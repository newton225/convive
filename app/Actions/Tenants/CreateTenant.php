<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Throwable;

class CreateTenant
{
    public function __construct(
        private CreateStarterProfiles $createStarterProfiles,
        private CreateStarterUnits $createStarterUnits,
    ) {
        //
    }

    /**
     * Create a new tenant, seed its starter profiles and make the user its owner.
     *
     * `Tenant::create()` declenche `TenantCreated`, qui provisionne et migre la base du
     * locataire de facon synchrone (voir `App\Providers\TenancyServiceProvider`) : par le
     * retour de cet appel, la base existe deja et est deja a jour. Ce provisionnement n'est
     * pas couvert par la transaction ci-dessous, qui ne porte que sur les ecritures
     * centrales : un echec plus loin annule l'appartenance et l'affectation, pas la base
     * physique du locataire. Elle est donc effacee ici des que la creation echoue : restee
     * orpheline, elle bloquait toute creation suivante, SQLite reprenant le meme numero
     * d'organisation (incident du 2026-10-04, « Database tenant16.sqlite already exists »).
     * L'ecran de sante technique signale tout orphelin qui echapperait malgre tout
     * (`OrphanTenantDatabasesCheck`).
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Tenant
    {
        $tenant = new Tenant([
            'name' => $name,
            'is_personal' => $isPersonal,
        ]);

        try {
            // Hors transaction : PostgreSQL refuse `CREATE DATABASE` dans un bloc de transaction.
            // La ligne de l'organisation n'est donc plus annulee par un retour en arriere, elle
            // est retiree a la main plus bas si la suite echoue.
            $tenant->save();

            return DB::transaction(fn () => $this->provision($tenant, $user));
        } catch (TenantDatabaseAlreadyExistsException $exception) {
            // Le fichier trouve en place n'est pas celui de cette tentative : il n'est jamais efface
            // a l'aveugle. L'ecran de sante technique le signale comme orphelin.
            if ($tenant->exists) {
                $tenant->forceDelete();
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($tenant->exists) {
                $this->deleteDatabase($tenant);
                $tenant->forceDelete();
            }

            throw $exception;
        }
    }

    private function provision(Tenant $tenant, User $user): Tenant
    {
        $ownerProfile = $tenant->run(function () {
            $ownerProfile = $this->createStarterProfiles->handle();
            $this->createStarterUnits->handle();

            return $ownerProfile;
        });

        $tenant->addMember($user, $ownerProfile);

        $user->switchTenant($tenant);

        return $tenant;
    }

    /**
     * Remove the physical database of a tenant whose creation failed, if it was created.
     */
    private function deleteDatabase(Tenant $tenant): void
    {
        $manager = $tenant->database()->manager();

        if (! $manager->databaseExists($tenant->database()->getName())) {
            return;
        }

        // Sous Windows, un fichier SQLite encore ouvert ne s'efface pas : la connexion du locataire
        // est d'abord liberee.
        DB::purge('tenant');
        gc_collect_cycles();

        $manager->deleteDatabase($tenant);
    }
}
