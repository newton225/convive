<?php

namespace Tests\Feature\Console;

use App\Actions\Events\SaveEvent;
use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Enums\InvoiceStatus;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\ShowcaseEvent;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Billing\PaymentOverdue;
use App\Notifications\Events\AnnouncementWithdrawnByEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le recouvrement et la moderation de la vitrine (README ecrans 29 et 32) : la console liste les
 * impayes reels et relance a la main, une fois par jour au plus ; elle retire une annonce abusive
 * avec un motif transmis a l'organisation, sans toucher a l'evenement.
 */
class RecoveryAndShowcaseTest extends TestCase
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
    }

    private function pastDue(int $daysAgo = 4): Subscription
    {
        return Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::PastDue,
            'currency' => 'XOF',
            'past_due_since' => now()->subDays($daysAgo),
        ]);
    }

    public function test_les_revenus_comptent_les_abonnements_a_jour_et_ce_qui_a_ete_encaisse(): void
    {
        $plan = Plan::ensure(PlanCode::Association);
        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'currency' => 'XOF',
        ]);

        $invoice = fn (string $number, InvoiceStatus $status, $paidAt) => Invoice::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'number' => $number,
            'amount' => 45000,
            'currency' => 'XOF',
            'status' => $status,
            'issued_at' => $paidAt ?? now(),
            'paid_at' => $paidAt,
        ]);

        $invoice('CNV-TEST-0101', InvoiceStatus::Paid, now());
        $invoice('CNV-TEST-0102', InvoiceStatus::Paid, now()->subMonthNoOverflow()->startOfMonth()->addDays(2));
        // Une facture en echec n'est pas un encaissement.
        $invoice('CNV-TEST-0103', InvoiceStatus::Failed, null);

        $this->actingAs($this->founder)
            ->get(route('console.recovery'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('revenue.subscribers', 1)
                ->where('revenue.recurring.0.currency', 'XOF')
                ->where('revenue.recurring.0.amount', $plan->monthly_price)
                ->where('revenue.collectedThisMonth.0.amount', 45000)
                ->where('revenue.collectedLastMonth.0.amount', 45000)
                ->where('revenue.byPlan.0.count', 1),
            );
    }

    public function test_un_abonnement_en_impaye_ne_compte_pas_dans_le_revenu_recurrent(): void
    {
        $this->pastDue(daysAgo: 4);

        $this->actingAs($this->founder)
            ->get(route('console.recovery'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('revenue.subscribers', 0)
                ->has('revenue.recurring', 0),
            );
    }

    public function test_le_recouvrement_liste_les_impayes_reels_et_leur_suspension_a_venir(): void
    {
        // A la seconde : la base ne garde pas les microsecondes.
        $this->freezeSecond();
        $this->pastDue(daysAgo: 4);

        $this->actingAs($this->founder)
            ->get(route('console.recovery'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isSample', false)
                ->has('unpaid', 1)
                ->where('unpaid.0.slug', $this->tenant->slug)
                ->where('unpaid.0.status', 'past_due')
                ->where('unpaid.0.amount', Plan::ensure(PlanCode::Association)->monthly_price)
                ->where('unpaid.0.suspendsAt', now()->subDays(4)->addDays(10)->toISOString())
                ->where('amountsDue.0.currency', 'XOF'),
            );
    }

    public function test_les_factures_en_echec_figurent_au_recouvrement(): void
    {
        $subscription = $this->pastDue();

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'number' => 'CNV-TEST-0001',
            'amount' => 45000,
            'currency' => 'XOF',
            'status' => InvoiceStatus::Failed,
            'issued_at' => now(),
        ]);

        $this->actingAs($this->founder)
            ->get(route('console.recovery'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('failedPayments', 1)
                ->where('failedPayments.0.amount', 45000)
                ->where('unpaid.0.amount', 45000),
            );
    }

    public function test_une_relance_part_a_ceux_qui_gerent_l_abonnement_et_va_au_journal(): void
    {
        Notification::fake();
        $this->pastDue();

        $this->actingAs($this->founder)
            ->post(route('console.recovery.remind', $this->tenant))
            ->assertRedirect(route('console.recovery'));

        Notification::assertSentTo($this->owner, PaymentOverdue::class);
        $this->assertSame('payment_reminder_sent', ConsoleActionLog::where('tenant_id', $this->tenant->id)->sole()->type);
    }

    public function test_une_seule_relance_manuelle_par_jour(): void
    {
        Notification::fake();
        $this->pastDue();

        $this->actingAs($this->founder)->post(route('console.recovery.remind', $this->tenant));

        $this->actingAs($this->founder)
            ->post(route('console.recovery.remind', $this->tenant))
            ->assertSessionHasErrors('organisation');

        Notification::assertSentToTimes($this->owner, PaymentOverdue::class, 1);
    }

    public function test_une_organisation_a_jour_ne_se_relance_pas(): void
    {
        Notification::fake();

        $this->actingAs($this->founder)
            ->post(route('console.recovery.remind', $this->tenant))
            ->assertSessionHasErrors('organisation');

        Notification::assertNothingSent();
    }

    public function test_le_support_n_ouvre_ni_le_recouvrement_ni_la_vitrine(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)->get(route('console.recovery'))->assertForbidden();
        $this->actingAs($support)->post(route('console.recovery.remind', $this->tenant))->assertForbidden();
        $this->actingAs($support)->get(route('console.showcase'))->assertForbidden();
    }

    private function announce(): ShowcaseEvent
    {
        $this->tenant->update(['subdomain' => 'convive']);

        $this->tenant->run(function () {
            $event = Event::factory()->published()->create(['name' => 'Diner de gala']);
            app(SaveEvent::class)->announce($event);
        });

        return ShowcaseEvent::sole();
    }

    public function test_la_vitrine_liste_les_annonces_reelles(): void
    {
        $announcement = $this->announce();

        $this->actingAs($this->founder)
            ->get(route('console.showcase'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isSample', false)
                ->has('announcements', 1)
                ->where('announcements.0.id', $announcement->id)
                ->where('announcements.0.eventName', 'Diner de gala')
                ->where('withdrawn', []),
            );
    }

    public function test_le_retrait_d_une_annonce_exige_un_motif(): void
    {
        $announcement = $this->announce();

        $this->actingAs($this->founder)
            ->post(route('console.showcase.withdraw', $announcement), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(1, ShowcaseEvent::count());
    }

    public function test_une_annonce_retiree_quitte_la_vitrine_et_le_motif_part_a_l_organisation(): void
    {
        Notification::fake();
        $announcement = $this->announce();

        $this->actingAs($this->founder)
            ->post(route('console.showcase.withdraw', $announcement), ['reason' => 'Promesse de gains, contraire aux conditions.'])
            ->assertRedirect(route('console.showcase'));

        $this->assertSame(0, ShowcaseEvent::count());

        // L'evenement n'est plus annonce, mais il existe toujours.
        $event = $this->tenant->run(fn () => Event::sole());
        $this->assertNull($event->announced_at);

        Notification::assertSentTo(
            $this->owner,
            AnnouncementWithdrawnByEditor::class,
            fn (AnnouncementWithdrawnByEditor $mail) => $mail->reason === 'Promesse de gains, contraire aux conditions.',
        );

        $entry = ConsoleActionLog::where('type', 'announcement_withdrawn')->sole();
        $this->assertSame('Diner de gala', $entry->properties['event_name']);

        $this->actingAs($this->founder)
            ->get(route('console.showcase'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('announcements', [])
                ->where('withdrawn.0.eventName', 'Diner de gala'),
            );
    }

    public function test_un_compte_hors_de_l_equipe_ne_trouve_aucune_de_ces_routes(): void
    {
        $announcement = $this->announce();

        $this->actingAs($this->owner)->get(route('console.recovery'))->assertNotFound();
        $this->actingAs($this->owner)->get(route('console.showcase'))->assertNotFound();
        $this->actingAs($this->owner)
            ->post(route('console.showcase.withdraw', $announcement), ['reason' => 'Un motif assez long.'])
            ->assertNotFound();
    }
}
