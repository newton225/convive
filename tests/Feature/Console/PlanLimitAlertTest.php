<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Baisser une limite d'un plan depuis la console previent les organisations de ce plan qui la
 * depassent : sans cela, elles ne l'apprendraient qu'en voyant une publication, une inscription ou
 * une invitation refusee. Rien n'est ferme ni supprime ; seule l'alerte part.
 */
class PlanLimitAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->tenant->run(fn () => Event::factory()->open()->count(3)->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $plan = Plan::ensure(PlanCode::Association);

        return [
            'monthly_price' => $plan->monthly_price,
            'monthly_price_eur' => null,
            'monthly_price_usd' => null,
            'max_active_events' => $plan->max_active_events,
            'max_registrations' => $plan->max_registrations,
            'max_members' => $plan->max_members,
            'has_reconciliation' => true,
            'has_reports' => true,
            ...$overrides,
        ];
    }

    private function update(array $overrides): void
    {
        $this->actingAs($this->founder)
            ->patch(route('console.plans.update', 'association'), $this->payload($overrides))
            ->assertSessionHasNoErrors();
    }

    public function test_une_organisation_qui_depasse_la_limite_abaissee_est_prevenue(): void
    {
        Notification::fake();

        $this->update(['max_active_events' => 2]);

        Notification::assertSentTo(
            $this->owner,
            TenantAlert::class,
            fn (TenantAlert $alert) => $alert->type === NotificationType::PlanLimitsLowered
                && $alert->tenantId === $this->tenant->id
                && $alert->params['plan'] === 'Association',
        );
    }

    public function test_ses_evenements_ouverts_ne_sont_ni_fermes_ni_supprimes(): void
    {
        Notification::fake();

        $this->update(['max_active_events' => 1]);

        $this->assertSame(3, $this->tenant->run(fn () => Event::where('status', EventStatus::Open)->count()));
    }

    public function test_une_organisation_qui_reste_sous_la_limite_n_est_pas_prevenue(): void
    {
        Notification::fake();

        $this->update(['max_active_events' => 3]);

        Notification::assertNothingSent();
    }

    public function test_relever_une_limite_ne_previent_personne(): void
    {
        Notification::fake();

        $this->update(['max_active_events' => 50]);

        Notification::assertNothingSent();
    }

    public function test_une_organisation_d_un_autre_plan_n_est_pas_prevenue(): void
    {
        Notification::fake();

        $this->actingAs($this->founder)
            ->patch(route('console.plans.update', 'institution'), $this->payload(['max_active_events' => 1]))
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }
}
