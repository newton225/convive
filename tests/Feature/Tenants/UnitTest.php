<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    private function unitOf(Tenant $tenant, string $name): Unit
    {
        return $tenant->asCurrent(fn () => Unit::where('name', $name)->firstOrFail());
    }

    public function test_l_ouverture_d_un_espace_cree_les_unites_de_depart(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $this->assertSame(
            Unit::Starters,
            $tenant->asCurrent(fn () => Unit::ordered()->pluck('name')->all()),
        );
    }

    public function test_aucune_est_une_unite_a_part_entiere(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $this->assertTrue($this->unitOf($tenant, Unit::None)->isNone());
        $this->assertTrue($this->unitOf($tenant, Unit::None)->is_active);
    }

    public function test_deux_organisations_ont_des_unites_distinctes(): void
    {
        $first = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Premiere');
        $second = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Seconde');

        // Ne pas comparer les identifiants : chaque locataire vit dans sa propre base, donc son
        // propre auto-increment (voir CLAUDE.md, Multi-locataire), et les unites de depart
        // etant creees dans le meme ordre pour toute organisation, les identifiants coincident
        // en pratique. Ce qui prouve l'absence de lien, c'est qu'une modification cote premiere
        // organisation ne se propage pas a la seconde.
        $firstUnit = $this->unitOf($first, 'QODESH');
        $secondUnit = $this->unitOf($second, 'QODESH');

        $first->asCurrent(fn () => $firstUnit->update(['name' => 'QODESH RENOMMEE']));

        $this->assertSame('QODESH RENOMMEE', $first->asCurrent(fn () => $firstUnit->fresh())->name);
        $this->assertSame('QODESH', $second->asCurrent(fn () => $secondUnit->fresh())->name);
    }

    public function test_chaque_locataire_ne_voit_que_ses_propres_unites(): void
    {
        $first = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Premiere');
        $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Seconde');

        // Chaque locataire vit dans sa propre base (voir CLAUDE.md, « Multi-locataire ») : lire
        // les unites du premier ne peut pas remonter celles du second, sans filtre ecrit a la
        // main, puisqu'il n'existe pas de table partagee ou l'un pourrait fuiter vers l'autre.
        $this->assertCount(count(Unit::Starters), $first->asCurrent(fn () => Unit::all()));
    }

    public function test_une_lecture_sans_locataire_resolu_echoue(): void
    {
        $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        // Sans tenancy initialisee, la connexion par defaut reste la base centrale, qui n'a pas
        // de table `units` : l'absence de locataire resolu echoue bruyamment plutot que de
        // rendre les donnees d'un autre locataire ou une liste vide trompeuse.
        $this->expectException(QueryException::class);

        Unit::count();
    }

    public function test_une_creation_sans_locataire_resolu_echoue(): void
    {
        $this->expectException(QueryException::class);

        Unit::create(['name' => 'ORPHELINE']);
    }

    public function test_le_proprietaire_voit_l_ecran_des_unites(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('tenants.units.index', $tenant))
            ->assertOk();
    }

    public function test_un_locataire_tiers_recoit_404_sur_les_unites(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('tenants.units.index', $tenant))
            ->assertNotFound();
    }

    public function test_le_proprietaire_ajoute_une_unite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.units.store', $tenant), ['name' => 'BETHEL'])
            ->assertRedirect();

        $this->assertNotNull($this->unitOf($tenant, 'BETHEL'));
    }

    public function test_une_unite_en_double_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.units.store', $tenant), ['name' => 'QODESH'])
            ->assertSessionHasErrors('name');
    }

    public function test_une_unite_du_meme_nom_reste_possible_dans_une_autre_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Autre');
        $other->asCurrent(fn () => Unit::where('name', 'QODESH')->delete());

        $this->actingAs($owner)
            ->post(route('tenants.units.store', $other), ['name' => 'QODESH'])
            ->assertNotFound();

        $this->assertNotNull($this->unitOf($tenant, 'QODESH'));
    }

    public function test_le_proprietaire_renomme_une_unite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $unit = $this->unitOf($tenant, 'CHOSEN');

        $this->actingAs($owner)
            ->patch(route('tenants.units.update', [$tenant, $unit]), [
                'name' => 'CHOISIS',
                'is_active' => true,
                'position' => 4,
            ])
            ->assertRedirect();

        $this->assertSame('CHOISIS', $tenant->asCurrent(fn () => $unit->fresh())->name);
    }

    public function test_une_unite_desactivee_n_est_plus_proposee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $unit = $this->unitOf($tenant, 'CHOSEN');

        $this->actingAs($owner)
            ->patch(route('tenants.units.update', [$tenant, $unit]), [
                'name' => 'CHOSEN',
                'is_active' => false,
                'position' => 4,
            ])
            ->assertRedirect();

        $this->assertNotContains(
            'CHOSEN',
            $tenant->asCurrent(fn () => Unit::active()->pluck('name')->all()),
        );
    }

    public function test_une_unite_d_un_autre_locataire_ne_peut_pas_etre_modifiee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Autre');

        // Une unite creee au-dela des unites de depart : celles-ci naissent dans le meme ordre
        // pour toute organisation, leurs identifiants coincident donc d'une base a l'autre
        // (voir CLAUDE.md, Multi-locataire), et reutiliser 'QODESH' resoudrait par hasard
        // l'unite locale de $tenant au lieu de detecter l'acces croise.
        $foreign = $other->asCurrent(fn () => Unit::create(['name' => 'ETRANGERE', 'position' => 99]));

        $this->actingAs($owner)
            ->patch(route('tenants.units.update', [$tenant, $foreign]), [
                'name' => 'DETOURNEE',
                'is_active' => true,
                'position' => 0,
            ])
            ->assertNotFound();

        $this->assertSame('ETRANGERE', $other->asCurrent(fn () => $foreign->fresh())->name);
    }

    public function test_sans_la_permission_les_unites_ne_sont_pas_modifiables(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantLegal]);

        $this->actingAs($member)
            ->post(route('tenants.units.store', $tenant), ['name' => 'BETHEL'])
            ->assertForbidden();
    }

    public function test_le_proprietaire_supprime_une_unite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $unit = $this->unitOf($tenant, 'CHOSEN');

        $this->actingAs($owner)
            ->delete(route('tenants.units.destroy', [$tenant, $unit]))
            ->assertRedirect();

        $tenant->asCurrent(fn () => $this->assertDatabaseMissing('units', ['id' => $unit->id]));
    }

    public function test_la_derniere_unite_ne_peut_pas_etre_supprimee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $tenant->asCurrent(fn () => Unit::where('name', '!=', Unit::None)->delete());
        $last = $this->unitOf($tenant, Unit::None);

        $this->actingAs($owner)
            ->delete(route('tenants.units.destroy', [$tenant, $last]))
            ->assertSessionHasErrors('unit');

        $tenant->asCurrent(fn () => $this->assertDatabaseHas('units', ['id' => $last->id]));
    }

    public function test_la_modification_d_une_unite_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $unit = $this->unitOf($tenant, 'CHOSEN');

        $this->actingAs($owner)
            ->patch(route('tenants.units.update', [$tenant, $unit]), [
                'name' => 'CHOISIS',
                'is_active' => true,
                'position' => 4,
            ]);

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'unit.updated')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('CHOSEN', $activity->properties['old']['name']);
    }
}
