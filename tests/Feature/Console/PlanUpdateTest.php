<?php

namespace Tests\Feature\Console;

use App\Enums\ConsoleProfile;
use App\Enums\PlanCode;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le catalogue des plans (README ecran 30) : la console regle les prix et les quotas. Un champ
 * vide veut dire « sur devis » pour un prix et « illimite » pour un quota ; chaque modification va
 * au journal central avec l'avant et l'apres.
 */
class PlanUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'monthly_price' => 45000,
            'monthly_price_eur' => '69.50',
            'monthly_price_usd' => '79',
            'max_active_events' => 8,
            'max_registrations' => 1500,
            'max_members' => null,
            'has_reconciliation' => true,
            'has_reports' => false,
            ...$overrides,
        ];
    }

    public function test_un_fondateur_modifie_les_prix_et_les_quotas_d_un_plan(): void
    {
        $this->actingAs($this->founder)
            ->patch(route('console.plans.update', 'association'), $this->payload())
            ->assertRedirect(route('console.plans'));

        $plan = Plan::ensure(PlanCode::Association);

        $this->assertSame(45000, $plan->monthly_price);
        // L'euro et le dollar se saisissent en unites et se stockent en centimes.
        $this->assertSame(6950, $plan->monthly_price_eur);
        $this->assertSame(7900, $plan->monthly_price_usd);
        $this->assertSame(8, $plan->max_active_events);
        $this->assertSame(1500, $plan->max_registrations);
        $this->assertNull($plan->max_members);
        $this->assertTrue($plan->has_reconciliation);
        $this->assertFalse($plan->has_reports);
    }

    public function test_la_modification_va_au_journal_central_avec_l_avant_et_l_apres(): void
    {
        $before = Plan::ensure(PlanCode::Association)->max_active_events;

        $this->actingAs($this->founder)
            ->patch(route('console.plans.update', 'association'), $this->payload());

        $entry = ConsoleActionLog::where('type', 'plan_updated')->sole();

        $this->assertSame($this->founder->id, $entry->actor_id);
        $this->assertSame('association', $entry->properties['plan']);
        $this->assertSame($before, $entry->properties['old']['max_active_events']);
        $this->assertSame(8, $entry->properties['attributes']['max_active_events']);
    }

    public function test_un_quota_nul_ou_negatif_est_refuse(): void
    {
        $this->actingAs($this->founder)
            ->patch(route('console.plans.update', 'association'), $this->payload(['max_active_events' => 0]))
            ->assertSessionHasErrors('max_active_events');

        $this->actingAs($this->founder)
            ->patch(route('console.plans.update', 'association'), $this->payload(['monthly_price' => -1]))
            ->assertSessionHasErrors('monthly_price');
    }

    public function test_un_plan_inconnu_recoit_404(): void
    {
        $this->actingAs($this->founder)
            ->patch(route('console.plans.update', 'platine'), $this->payload())
            ->assertNotFound();
    }

    public function test_le_support_ne_modifie_pas_les_plans_et_la_comptabilite_le_peut(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        ConsoleOperator::create(['email' => 'compta@convive.test', 'profile' => ConsoleProfile::Accounting]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);
        $accounting = User::factory()->withTwoFactor()->create(['email' => 'compta@convive.test']);

        $this->actingAs($support)
            ->patch(route('console.plans.update', 'association'), $this->payload())
            ->assertForbidden();

        $this->actingAs($accounting)
            ->patch(route('console.plans.update', 'association'), $this->payload())
            ->assertRedirect(route('console.plans'));
    }

    public function test_un_compte_hors_de_l_equipe_ne_trouve_pas_la_route(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->patch(route('console.plans.update', 'association'), $this->payload())
            ->assertNotFound();
    }
}
