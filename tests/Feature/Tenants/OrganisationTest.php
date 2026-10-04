<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OrganisationTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function completeLegalIdentity(array $overrides = []): array
    {
        return array_merge([
            'display_name' => 'Association Convive',
            'legal_name' => 'Association Convive Cote d Ivoire',
            'legal_form' => LegalForm::Association->value,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2020-B-12345',
            'tax_number' => '1234567 A',
            'address' => 'Rue des Jardins, Cocody',
            'city' => 'Abidjan',
            'country' => 'CI',
            'email' => 'contact@convive.ci',
            'phone' => '+225 07 00 00 00 00',
        ], $overrides);
    }

    public function test_le_formulaire_d_organisation_s_affiche_pour_un_proprietaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('tenants.organisation.edit', $tenant))
            ->assertOk();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_formulaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.organisation.edit', $tenant))
            ->assertNotFound();
    }

    public function test_un_locataire_tiers_recoit_404_sur_l_identite_legale(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity())
            ->assertNotFound();
    }

    public function test_un_locataire_tiers_recoit_404_sur_la_marque(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->patch(route('tenants.organisation.branding', $tenant), ['primary_color' => '#112233'])
            ->assertNotFound();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_sous_domaine(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'convive-ci'])
            ->assertNotFound();
    }

    public function test_le_proprietaire_enregistre_l_identite_legale(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity())
            ->assertRedirect();

        $branding = $tenant->fresh()->branding;

        $this->assertSame('Association Convive Cote d Ivoire', $branding->legal_name);
        $this->assertSame(LegalForm::Association, $branding->legal_form);
        $this->assertSame('CI-ABJ-2020-B-12345', $branding->registration_number);
    }

    public function test_le_pays_s_enregistre_sous_son_code_et_un_nom_libre_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity(['country' => 'sn']))
            ->assertSessionHasNoErrors();

        $this->assertSame('SN', $tenant->fresh()->branding->country);

        foreach (["Cote d'Ivoire", 'ZZ'] as $country) {
            $this->actingAs($owner)
                ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity(['country' => $country]))
                ->assertSessionHasErrors('country');
        }

        $this->assertSame('SN', $tenant->fresh()->branding->country);
    }

    public function test_sans_la_permission_legale_l_identite_n_est_pas_modifiable(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantBranding]);

        $this->actingAs($member)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity())
            ->assertForbidden();

        $this->assertNull($tenant->fresh()->branding);
    }

    public function test_sans_la_permission_de_marque_les_couleurs_ne_sont_pas_modifiables(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantLegal]);

        $this->actingAs($member)
            ->patch(route('tenants.organisation.branding', $tenant), ['primary_color' => '#112233'])
            ->assertForbidden();
    }

    public function test_sans_la_permission_de_domaine_le_sous_domaine_n_est_pas_modifiable(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantLegal]);

        $this->actingAs($member)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'convive-ci'])
            ->assertForbidden();

        $this->assertNull($tenant->fresh()->subdomain);
    }

    public function test_une_forme_juridique_hors_catalogue_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity([
                'legal_form' => 'multinationale',
            ]))
            ->assertSessionHasErrors('legal_form');
    }

    public function test_un_email_invalide_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity([
                'email' => 'pas-une-adresse',
            ]))
            ->assertSessionHasErrors('email');
    }

    public function test_une_couleur_hors_format_hexadecimal_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.branding', $tenant), [
                'primary_color' => 'bordeaux',
                'secondary_color' => '#c9a227',
            ])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_les_couleurs_de_marque_ont_des_valeurs_par_defaut(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->assertSame(
            ['primary' => '#7b1e3a', 'secondary' => '#c9a227'],
            $tenant->brandingOrCreate()->colors(),
        );
    }

    public function test_un_sous_domaine_reserve_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'admin'])
            ->assertSessionHasErrors('subdomain');

        $this->assertNull($tenant->fresh()->subdomain);
    }

    public function test_un_sous_domaine_deja_pris_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Autre organisation');
        $other->update(['subdomain' => 'convive-ci']);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'convive-ci'])
            ->assertSessionHasErrors('subdomain');
    }

    public function test_un_sous_domaine_est_normalise_en_minuscules(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'Convive-CI'])
            ->assertRedirect();

        $this->assertSame('convive-ci', $tenant->fresh()->subdomain);
    }

    public function test_un_sous_domaine_avec_un_caractere_interdit_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'convive.ci'])
            ->assertSessionHasErrors('subdomain');
    }

    public function test_une_organisation_incomplete_ne_peut_pas_publier(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity([
                'tax_number' => '',
            ]));

        $this->assertFalse($tenant->fresh()->isReadyToPublish());
        $this->assertContains('tax_number', $tenant->fresh()->branding->missingBeforePublishing());
    }

    public function test_une_organisation_complete_avec_un_sous_domaine_peut_publier(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity());

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'convive-ci']);

        $this->assertTrue($tenant->fresh()->isReadyToPublish());
    }

    public function test_un_espace_personnel_reste_utilisable_sans_identite_legale(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $personal = $owner->personalTenant();

        $this->actingAs($owner)
            ->get(route('tenants.organisation.edit', $personal))
            ->assertOk();

        $this->assertFalse($personal->isReadyToPublish());
    }

    public function test_l_identite_legale_est_enregistrable_par_morceaux(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), ['display_name' => 'Convive'])
            ->assertRedirect();

        $this->assertSame('Convive', $tenant->fresh()->branding->display_name);
    }

    public function test_la_modification_de_l_identite_legale_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.legal', $tenant), $this->completeLegalIdentity());

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'organisation.legal_updated')
            ->latest('id')
            ->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame($tenant->id, $activity->properties['tenant_id']);
    }

    public function test_la_modification_du_sous_domaine_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'convive-ci']);

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'organisation.subdomain_updated')
            ->latest('id')
            ->first());

        $this->assertNotNull($activity);
        $this->assertSame('convive-ci', $activity->properties['attributes']['subdomain']);
    }
}
