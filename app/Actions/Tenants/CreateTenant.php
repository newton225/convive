<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
     * physique du locataire, qui reste alors orpheline. Rare (les operations qui suivent sont
     * simples), mais reel.
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Tenant
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $tenant = Tenant::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $ownerProfile = $tenant->run(function () {
                $ownerProfile = $this->createStarterProfiles->handle();
                $this->createStarterUnits->handle();

                return $ownerProfile;
            });

            $tenant->addMember($user, $ownerProfile);

            $user->switchTenant($tenant);

            return $tenant;
        });
    }
}
