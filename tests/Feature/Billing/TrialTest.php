<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\ConsoleActionLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\TrialSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La periode d'essai (README section 3) : tout espace neuf est a l'essai sur le plan Association,
 * sans date de fin tant que l'editeur n'en pose pas (decision du proprietaire du 2026-10-01). Un
 * abonnement l'emporte sur l'essai ; une fois l'essai echu, le plan par defaut s'applique et rien
 * n'est supprime.
 */
class TrialTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        // Un essai ouvert, sans date de fin : le cas que ces tests decrivent. La duree reglee et
        // ses rappels sont dans `TrialSettingsTest`.
        $this->trial(enabled: true);

        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');
    }

    private function trial(bool $enabled): void
    {
        $settings = app(TrialSettings::class);
        $settings->enabled = $enabled;
        $settings->days = null;
        $settings->plan = 'association';
        $settings->save();
    }

    public function test_un_espace_neuf_est_a_l_essai_sans_date_de_fin_sur_le_plan_association(): void
    {
        $this->assertTrue($this->tenant->isOnTrial());
        $this->assertNull($this->tenant->trial_ends_at);
        $this->assertSame('association', $this->tenant->plan()->code);
    }

    public function test_un_essai_echu_rend_la_main_au_plan_par_defaut(): void
    {
        $this->tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();

        $this->assertFalse($this->tenant->fresh()->isOnTrial());
        $this->assertSame(PlanCode::default()->value, $this->tenant->fresh()->plan()->code);
    }

    public function test_un_abonnement_l_emporte_sur_l_essai(): void
    {
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::ensure(PlanCode::Essential)->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $tenant = $this->tenant->fresh();

        $this->assertFalse($tenant->isOnTrial());
        $this->assertSame('essential', $tenant->plan()->code);
    }

    public function test_sans_essai_active_le_plan_par_defaut_s_applique(): void
    {
        $this->trial(enabled: false);

        $this->assertFalse($this->tenant->isOnTrial());
        $this->assertSame(PlanCode::default()->value, $this->tenant->plan()->code);
    }

    public function test_l_editeur_pose_une_date_de_fin_a_l_essai(): void
    {
        $this->freezeTime();
        $endsAt = now()->addDays(20)->toDateString();

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.trial.update', $this->tenant), ['ends_at' => $endsAt])
            ->assertRedirect(route('console.organisations.show', $this->tenant->slug));

        $tenant = $this->tenant->fresh();

        $this->assertSame($endsAt, $tenant->trial_ends_at?->toDateString());
        $this->assertTrue($tenant->isOnTrial());
        $this->assertSame('trial_extended', ConsoleActionLog::where('tenant_id', $tenant->id)->sole()->type);
    }

    public function test_l_editeur_rend_un_essai_echu_a_nouveau_sans_fin(): void
    {
        $this->tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.trial.update', $this->tenant), ['ends_at' => null])
            ->assertSessionHasNoErrors();

        $tenant = $this->tenant->fresh();

        $this->assertNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->isOnTrial());
    }

    public function test_une_date_de_fin_passee_est_refusee(): void
    {
        $this->actingAs($this->founder)
            ->patch(route('console.organisations.trial.update', $this->tenant), ['ends_at' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_un_essai_ne_s_offre_pas_a_une_organisation_abonnee(): void
    {
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.trial.update', $this->tenant), ['ends_at' => now()->addDays(20)->toDateString()])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_la_fiche_de_la_console_montre_l_essai(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.organisations.show', $this->tenant->slug))
            ->assertInertia(fn ($page) => $page
                ->where('organisation.status', 'trial')
                ->where('organisation.onTrial', true)
                ->where('organisation.trialEndsAt', null)
                ->where('organisation.plan', 'association'),
            );
    }
}
