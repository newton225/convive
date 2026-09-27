<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La carte « Premiers pas » du tableau de bord : ce qu'il reste a faire pour publier un premier
 * evenement, coche d'apres les donnees reelles, jamais d'apres un clic.
 */
class DashboardGettingStartedTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->owner->switchTenant($this->tenant);
    }

    private function dashboard(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('dashboard', $this->tenant));
    }

    public function test_une_organisation_neuve_voit_toutes_les_etapes_a_faire(): void
    {
        $this->dashboard($this->owner)
            ->assertInertia(fn (Assert $page) => $page
                ->where('gettingStarted.steps.0.key', 'identity')
                ->where('gettingStarted.steps.0.done', false)
                ->where('gettingStarted.steps.1.key', 'payment_account')
                ->where('gettingStarted.steps.2.key', 'event')
                ->where('gettingStarted.steps.3.key', 'publish')
                ->where('gettingStarted.steps.4.key', 'team')
                ->where('gettingStarted.completed', 0));
    }

    public function test_les_etapes_se_cochent_d_apres_les_donnees(): void
    {
        $this->tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $this->tenant->update(['subdomain' => 'convive-ci']);
        $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            Event::factory()->create();
        });

        $this->dashboard($this->owner)
            ->assertInertia(fn (Assert $page) => $page
                ->where('gettingStarted.steps.0.done', true)
                ->where('gettingStarted.steps.1.done', true)
                ->where('gettingStarted.steps.2.done', true)
                ->where('gettingStarted.steps.3.done', false)
                ->where('gettingStarted.completed', 3));
    }

    public function test_la_carte_disparait_quand_tout_est_fait(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->published()->create());
        $this->tenant->update(['subdomain' => 'convive-ci']);
        $this->tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $this->tenant->asCurrent(fn () => PaymentAccount::factory()->create());
        $this->joinWithPermissions($this->tenant, User::factory()->withTwoFactor()->create(), [TenantPermission::EventsView]);

        $this->dashboard($this->owner)
            ->assertInertia(fn (Assert $page) => $page->where('gettingStarted', null));
    }

    public function test_un_membre_qui_ne_peut_pas_creer_d_evenement_ne_voit_pas_la_carte(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);
        $member->switchTenant($this->tenant);

        $this->dashboard($member)
            ->assertInertia(fn (Assert $page) => $page->where('gettingStarted', null));
    }
}
