<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    private function profileOf(Tenant $tenant, string $name): Profile
    {
        // `permissions` charge d'avance : sans cela, la premiere lecture de cette relation
        // (par `permissionValues()` ou `holds()`) se ferait hors du contexte de locataire pose
        // par `run()`, une fois la connexion deja restauree.
        return $tenant->run(fn () => Profile::with('permissions')->where('name', $name)->firstOrFail());
    }

    public function test_l_ouverture_d_un_espace_cree_les_quatre_profils_de_depart(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $this->assertEqualsCanonicalizing(
            [Profile::Owner, 'Tresorier', 'Hotesse', 'Lecture'],
            $tenant->run(fn () => Profile::pluck('name')->all()),
        );
    }

    public function test_le_createur_de_l_espace_recoit_le_profil_proprietaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->assertSame(Profile::Owner, $owner->tenantProfile($tenant)?->name);
    }

    public function test_le_profil_proprietaire_detient_toutes_les_permissions_du_catalogue(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $this->assertEqualsCanonicalizing(
            TenantPermission::values(),
            $this->profileOf($tenant, Profile::Owner)->permissionValues(),
        );
    }

    public function test_le_profil_proprietaire_est_le_seul_a_detenir_les_comptes_de_versement(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        foreach (['Tresorier', 'Hotesse', 'Lecture'] as $name) {
            $this->assertFalse(
                $this->profileOf($tenant, $name)->holds(TenantPermission::TenantPaymentAccounts),
                "Le profil {$name} ne doit pas detenir tenant.payment_accounts.",
            );
        }
    }

    public function test_deux_organisations_peuvent_avoir_un_profil_du_meme_nom(): void
    {
        $first = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Premiere organisation');
        $second = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Seconde organisation');

        // Ne pas comparer les identifiants : chaque locataire vit dans sa propre base, donc son
        // propre auto-increment (voir CLAUDE.md, Multi-locataire), et les profils de depart
        // etant crees dans le meme ordre pour toute organisation, les identifiants coincident
        // en pratique. Ce qui prouve l'absence de lien, c'est qu'une modification cote premiere
        // organisation ne se propage pas a la seconde.
        $firstTreasurer = $this->profileOf($first, 'Tresorier');
        $secondTreasurer = $this->profileOf($second, 'Tresorier');

        $first->run(fn () => $firstTreasurer->update(['name' => 'Grand Tresorier']));

        $this->assertSame('Grand Tresorier', $first->run(fn () => $firstTreasurer->fresh())->name);
        $this->assertSame('Tresorier', $second->run(fn () => $secondTreasurer->fresh())->name);
    }

    public function test_les_profils_d_un_autre_locataire_ne_sont_pas_visibles(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Organisation tierce');

        $this->actingAs($owner)
            ->get(route('tenants.profiles.index', $other))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('tenants.profiles.index', $tenant))
            ->assertOk();
    }

    public function test_un_profil_systeme_ne_peut_pas_etre_modifie(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $ownerProfile = $this->profileOf($tenant, Profile::Owner);

        $this->actingAs($owner)
            ->patch(route('tenants.profiles.update', [$tenant, $ownerProfile]), [
                'name' => 'Patron',
                'permissions' => [TenantPermission::EventsView->value],
            ])
            ->assertForbidden();

        $this->assertSame(Profile::Owner, $tenant->asCurrent(fn () => $ownerProfile->fresh())->name);
    }

    public function test_un_profil_systeme_ne_peut_pas_etre_supprime(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $ownerProfile = $this->profileOf($tenant, Profile::Owner);

        $this->actingAs($owner)
            ->delete(route('tenants.profiles.destroy', [$tenant, $ownerProfile]))
            ->assertForbidden();

        $tenant->asCurrent(fn () => $this->assertDatabaseHas('profiles', ['id' => $ownerProfile->id]));
    }

    public function test_un_profil_affecte_a_des_membres_ne_peut_pas_etre_supprime(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $treasurer = $this->profileOf($tenant, 'Tresorier');

        $member = User::factory()->withTwoFactor()->create();
        $tenant->addMember($member, $treasurer);

        $this->actingAs($owner)
            ->delete(route('tenants.profiles.destroy', [$tenant, $treasurer]))
            ->assertSessionHasErrors('profile');

        $tenant->asCurrent(fn () => $this->assertDatabaseHas('profiles', ['id' => $treasurer->id]));
    }

    public function test_un_profil_sans_membre_peut_etre_supprime(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $reader = $this->profileOf($tenant, 'Lecture');

        $this->actingAs($owner)
            ->delete(route('tenants.profiles.destroy', [$tenant, $reader]))
            ->assertRedirect();

        $tenant->asCurrent(fn () => $this->assertDatabaseMissing('profiles', ['id' => $reader->id]));
    }

    public function test_le_dernier_proprietaire_ne_peut_pas_perdre_son_profil(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $reader = $this->profileOf($tenant, 'Lecture');

        $this->actingAs($owner)
            ->patch(route('tenants.members.update', [$tenant, $owner]), [
                'profile_id' => $reader->id,
            ])
            ->assertForbidden();

        $this->assertSame(Profile::Owner, $owner->fresh()->tenantProfile($tenant)?->name);
    }

    public function test_on_ne_peut_pas_attribuer_une_permission_que_l_on_ne_detient_pas(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $manager = User::factory()->withTwoFactor()->create();
        $limited = $this->makeProfile($tenant, 'Gestionnaire', [
            TenantPermission::ProfilesManage,
            TenantPermission::EventsView,
        ]);
        $tenant->addMember($manager, $limited);

        $target = $this->profileOf($tenant, 'Lecture');

        $this->actingAs($manager)
            ->patch(route('tenants.profiles.update', [$tenant, $target]), [
                'name' => 'Lecture',
                'permissions' => [TenantPermission::BillingManage->value],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertFalse($tenant->asCurrent(
            fn () => $target->fresh()->holds(TenantPermission::BillingManage),
        ));
    }

    public function test_un_membre_ne_peut_pas_modifier_le_profil_qu_il_porte(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $manager = User::factory()->withTwoFactor()->create();
        $carried = $this->makeProfile($tenant, 'Gestionnaire', [
            TenantPermission::ProfilesManage,
            TenantPermission::EventsView,
        ]);
        $tenant->addMember($manager, $carried);

        $this->actingAs($manager)
            ->patch(route('tenants.profiles.update', [$tenant, $carried]), [
                'name' => 'Gestionnaire',
                'permissions' => [TenantPermission::EventsView->value],
            ])
            ->assertForbidden();
    }

    public function test_un_membre_ne_peut_pas_s_affecter_un_autre_profil(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $manager = User::factory()->withTwoFactor()->create();
        $carried = $this->makeProfile($tenant, 'Gestionnaire', [
            TenantPermission::ProfilesManage,
            TenantPermission::TeamView,
        ]);
        $tenant->addMember($manager, $carried);

        $this->actingAs($manager)
            ->patch(route('tenants.members.update', [$tenant, $manager]), [
                'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            ])
            ->assertForbidden();

        $this->assertSame('Gestionnaire', $manager->fresh()->tenantProfile($tenant)?->name);
    }

    public function test_un_profil_d_un_autre_locataire_ne_peut_pas_etre_affecte(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Organisation tierce');

        $member = User::factory()->withTwoFactor()->create();
        $tenant->addMember($member, $this->profileOf($tenant, 'Lecture'));

        // Un profil cree au-dela des quatre profils de depart : ceux-ci naissent dans le meme
        // ordre pour toute organisation, leurs identifiants coincident donc d'une base a
        // l'autre (voir CLAUDE.md, Multi-locataire), et reutiliser 'Lecture' ne prouverait rien
        // puisque `Rule::exists('profiles', 'id')` retomberait par hasard sur le profil
        // 'Lecture' local de $tenant plutot que d'echouer.
        $foreign = $this->makeProfile($other, 'Special', [TenantPermission::EventsView]);

        $this->actingAs($owner)
            ->patch(route('tenants.members.update', [$tenant, $member]), [
                'profile_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('profile_id');
    }

    public function test_une_modification_de_profil_prend_effet_immediatement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $reader = $this->profileOf($tenant, 'Lecture');
        $tenant->addMember($member, $reader);

        $this->assertFalse($member->hasTenantPermission($tenant, TenantPermission::EventsCreate));

        $this->actingAs($owner)
            ->patch(route('tenants.profiles.update', [$tenant, $reader]), [
                'name' => 'Lecture',
                'permissions' => [
                    TenantPermission::RegistrationsView->value,
                    TenantPermission::EventsCreate->value,
                ],
            ])
            ->assertRedirect();

        $this->assertTrue($member->fresh()->hasTenantPermission($tenant, TenantPermission::EventsCreate));
    }

    public function test_un_utilisateur_a_des_permissions_differentes_dans_chaque_organisation(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $first = $this->tenantOwnedBy($user, 'Premiere organisation');

        $second = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Seconde organisation');
        $second->addMember($user, $this->profileOf($second, 'Lecture'));

        $this->assertTrue($user->hasTenantPermission($first, TenantPermission::BillingManage));
        $this->assertFalse($user->hasTenantPermission($second, TenantPermission::BillingManage));
    }

    public function test_une_permission_hors_catalogue_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.profiles.update', [$tenant, $this->profileOf($tenant, 'Lecture')]), [
                'name' => 'Lecture',
                'permissions' => ['events.invent'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_la_creation_d_un_profil_est_journalisee_avec_l_acteur(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.profiles.store', $tenant), [
                'name' => 'Accueil',
                'description' => 'Equipe presente a l\'entree',
                'permissions' => [TenantPermission::ScanPerform->value],
            ])
            ->assertRedirect();

        $activity = $tenant->asCurrent(fn () => Activity::query()->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('created', $activity->event);
    }

    public function test_un_membre_sans_la_permission_ne_gere_pas_les_profils(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $tenant->addMember($member, $this->profileOf($tenant, 'Lecture'));

        $this->actingAs($member)
            ->post(route('tenants.profiles.store', $tenant), [
                'name' => 'Accueil',
                'permissions' => [TenantPermission::ScanPerform->value],
            ])
            ->assertForbidden();
    }

    /**
     * @param  array<int, TenantPermission>  $permissions
     */
    private function makeProfile(Tenant $tenant, string $name, array $permissions): Profile
    {
        return $tenant->run(function () use ($name, $permissions) {
            $profile = Profile::create([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            $profile->syncPermissions(array_map(fn (TenantPermission $p) => $p->value, $permissions));

            return $profile;
        });
    }
}
