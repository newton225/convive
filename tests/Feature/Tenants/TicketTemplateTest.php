<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Enums\TicketModel;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le gabarit du billet (README ecran 15) : modele et elements activables, reels, appliques au
 * billet que l'invite recoit (`App\Http\Controllers\Public\RegistrationController`), pas
 * seulement a l'apercu du back-office.
 */
class TicketTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function memberWith(TenantPermission ...$permissions): User
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, $permissions);

        return $member;
    }

    public function test_le_gabarit_du_billet_montre_la_marque_reelle_et_les_evenements(): void
    {
        $this->tenant->brandingOrCreate()->update([
            'display_name' => 'Convive CI',
            'primary_color' => '#112233',
            'secondary_color' => '#aabbcc',
            'representative_name' => 'Aya Kouassi',
        ]);
        $this->tenant->asCurrent(fn () => Event::factory()->count(2)->create());

        $this->actingAs($this->owner)
            ->get(route('tenants.ticket-template.edit', $this->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tenants/ticket-template')
                ->where('brand.displayName', 'Convive CI')
                ->where('brand.colors.primary', '#112233')
                ->where('brand.colors.secondary', '#aabbcc')
                ->where('brand.representative', 'Aya Kouassi')
                ->where('brand.logoUrl', null)
                ->where('model', 'classic')
                ->where('elements.logo', true)
                ->has('events', 2),
            );
    }

    public function test_le_gabarit_propose_le_fond_du_billet_et_l_organisation_ne_le_liste_pas(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.ticket-template.edit', $this->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('backgroundFile.value', 'ticket_background')
                ->where('backgroundFile.crop', ['width' => 1000, 'height' => 850])
                ->where('brand.backgroundUrl', null)
                ->where('bodyBackgroundFile.value', 'ticket_body_background')
                ->where('bodyBackgroundFile.crop', ['width' => 1000, 'height' => 1228])
                ->where('brand.bodyBackgroundUrl', null)
                ->where('sampleQrImage', fn (string $image) => str_starts_with($image, 'data:image/svg+xml;base64,')),
            );

        // Un reglage du billet, depose depuis son gabarit : pas une piece de l'identite de
        // l'organisation.
        $this->actingAs($this->owner)
            ->get(route('tenants.organisation.edit', $this->tenant))
            ->assertInertia(fn ($page) => $page->where(
                'brandFiles',
                fn ($files) => collect($files)->pluck('value')->intersect(['ticket_background', 'ticket_body_background'])->isEmpty(),
            ));
    }

    public function test_le_proprietaire_enregistre_le_gabarit(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('tenants.ticket-template.update', $this->tenant), [
                'ticket_model' => 'elegant',
                'ticket_element_logo' => true,
                'ticket_element_stamp' => false,
                'ticket_element_signature' => true,
                'ticket_element_companions' => false,
            ])
            ->assertRedirect(route('tenants.ticket-template.edit', $this->tenant));

        $branding = $this->tenant->fresh()->brandingOrCreate();

        $this->assertSame(TicketModel::Elegant, $branding->ticket_model);
        $this->assertFalse($branding->ticket_element_stamp);
        $this->assertFalse($branding->ticket_element_companions);
    }

    public function test_une_valeur_de_modele_hors_catalogue_est_refusee(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('tenants.ticket-template.update', $this->tenant), [
                'ticket_model' => 'flashy',
            ])
            ->assertSessionHasErrors('ticket_model');
    }

    public function test_un_membre_sans_la_permission_de_marque_n_enregistre_rien(): void
    {
        $this->actingAs($this->memberWith(TenantPermission::EventsView))
            ->patch(route('tenants.ticket-template.update', $this->tenant), [
                'ticket_model' => 'elegant',
            ])
            ->assertForbidden();
    }

    public function test_le_gabarit_du_billet_exige_la_permission_de_marque(): void
    {
        $this->actingAs($this->memberWith(TenantPermission::EventsView))
            ->get(route('tenants.ticket-template.edit', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_gabarit_du_billet(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('tenants.ticket-template.edit', $this->tenant))
            ->assertNotFound();
    }

    public function test_le_gabarit_s_ouvre_pour_une_organisation_qui_n_a_jamais_enregistre_sa_marque(): void
    {
        // Reproduit une erreur 500 : la fiche de marque creee a la volee ne portait pas les
        // valeurs par defaut de ses colonnes (`ticket_model`), posees par la base et jamais relues.
        $this->assertNull($this->tenant->branding()->first());

        $this->actingAs($this->owner)
            ->get(route('tenants.ticket-template.edit', $this->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('model', TicketModel::Classic->value)
                ->where('elements.logo', true),
            );
    }

    public function test_le_gabarit_ne_liste_pas_les_evenements_d_une_autre_organisation(): void
    {
        $otherOwner = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($otherOwner, 'Autre Association');
        $other->asCurrent(fn () => Event::factory()->create(['name' => 'Evenement etranger']));

        $this->actingAs($this->owner)
            ->get(route('tenants.ticket-template.edit', $this->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('events', 0));
    }
}
