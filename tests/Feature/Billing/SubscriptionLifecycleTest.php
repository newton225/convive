<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\MarkSubscriptionPaid;
use App\Actions\Billing\MarkSubscriptionPastDue;
use App\Actions\Billing\ProcessOverdueSubscriptions;
use App\Actions\Tenants\CreateTenant;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Billing\PaymentOverdue;
use App\Notifications\Billing\SubscriptionSuspended;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Le cycle de vie d'un abonnement (README section 3, « Facturation ») : echec de paiement, relance
 * a J+3, suspension de l'espace a J+10, retour a la normale au reglement. Etape 10.
 */
class SubscriptionLifecycleTest extends TestCase
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

    private function subscription(string $state = 'pastDue', int $daysAgo = 0): Subscription
    {
        return match ($state) {
            'pastDue' => Subscription::factory()->pastDue($daysAgo)->create(['tenant_id' => $this->tenant->id]),
            'suspended' => Subscription::factory()->suspended()->create(['tenant_id' => $this->tenant->id]),
            default => Subscription::factory()->create(['tenant_id' => $this->tenant->id]),
        };
    }

    public function test_un_echec_de_paiement_passe_l_abonnement_actif_en_impaye(): void
    {
        $subscription = $this->subscription('active');

        app(MarkSubscriptionPastDue::class)->handle($subscription);

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->past_due_since);
    }

    public function test_un_second_echec_ne_repousse_pas_le_compte_a_rebours(): void
    {
        $subscription = $this->subscription('pastDue', 2);
        $since = $subscription->past_due_since;

        app(MarkSubscriptionPastDue::class)->handle($subscription);

        $this->assertEquals($since->getTimestamp(), $subscription->fresh()->past_due_since->getTimestamp());
    }

    public function test_un_echec_sur_un_espace_deja_suspendu_ne_le_reactive_pas(): void
    {
        $subscription = $this->subscription('suspended');

        app(MarkSubscriptionPastDue::class)->handle($subscription);

        $this->assertSame(SubscriptionStatus::Suspended, $subscription->fresh()->status);
    }

    public function test_le_reglement_remet_l_abonnement_en_regle_et_leve_la_suspension(): void
    {
        $subscription = $this->subscription('suspended');
        $subscription->update(['overdue_reminder_sent_at' => now()->subDays(7)]);

        app(MarkSubscriptionPaid::class)->handle($subscription);

        $fresh = $subscription->fresh();

        $this->assertSame(SubscriptionStatus::Active, $fresh->status);
        $this->assertNull($fresh->past_due_since);
        $this->assertNull($fresh->overdue_reminder_sent_at);
        $this->assertNull($fresh->suspended_at);
        $this->assertFalse($this->tenant->fresh()->isSuspended());
    }

    public function test_le_reglement_enregistre_la_facture_une_seule_fois(): void
    {
        $subscription = $this->subscription('pastDue', 1);
        $invoice = [
            'stripe_invoice_id' => 'in_123',
            'number' => 'F-000123',
            'amount' => 25000,
            'hosted_invoice_url' => 'https://invoice.example/in_123',
        ];

        app(MarkSubscriptionPaid::class)->handle($subscription, $invoice);
        app(MarkSubscriptionPaid::class)->handle($subscription->fresh(), $invoice);

        $this->assertSame(1, $this->tenant->invoices()->count());

        $stored = $this->tenant->invoices()->firstOrFail();

        $this->assertSame(InvoiceStatus::Paid, $stored->status);
        $this->assertSame(25000, $stored->amount);
        $this->assertNotNull($stored->paid_at);
    }

    public function test_avant_trois_jours_d_impaye_rien_n_est_envoye(): void
    {
        Notification::fake();
        $this->subscription('pastDue', 2);

        $result = app(ProcessOverdueSubscriptions::class)->handle();

        $this->assertSame(['reminded' => 0, 'suspended' => 0], $result);
        Notification::assertNothingSent();
    }

    public function test_a_trois_jours_d_impaye_les_gestionnaires_de_l_abonnement_sont_relances(): void
    {
        Notification::fake();
        $subscription = $this->subscription('pastDue', 3);

        $manager = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $manager, [TenantPermission::BillingManage], 'Gestion');
        $reader = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $reader, [TenantPermission::EventsView], 'Lecteur');

        $result = app(ProcessOverdueSubscriptions::class)->handle();

        $this->assertSame(1, $result['reminded']);
        Notification::assertSentTo($this->owner, PaymentOverdue::class);
        Notification::assertSentTo($manager, PaymentOverdue::class);
        Notification::assertNotSentTo($reader, PaymentOverdue::class);
        $this->assertNotNull($subscription->fresh()->overdue_reminder_sent_at);
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);
    }

    public function test_la_relance_n_est_envoyee_qu_une_fois(): void
    {
        Notification::fake();
        $this->subscription('pastDue', 4);

        app(ProcessOverdueSubscriptions::class)->handle();
        app(ProcessOverdueSubscriptions::class)->handle();

        Notification::assertSentToTimes($this->owner, PaymentOverdue::class, 1);
    }

    public function test_a_dix_jours_d_impaye_l_espace_est_suspendu_et_l_equipe_prevenue(): void
    {
        Notification::fake();
        $subscription = $this->subscription('pastDue', 10);

        $result = app(ProcessOverdueSubscriptions::class)->handle();

        $this->assertSame(1, $result['suspended']);
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->suspended_at);
        Notification::assertSentTo($this->owner, SubscriptionSuspended::class);
    }

    public function test_un_espace_deja_suspendu_n_est_pas_suspendu_deux_fois(): void
    {
        Notification::fake();
        $this->subscription('suspended');

        $result = app(ProcessOverdueSubscriptions::class)->handle();

        $this->assertSame(['reminded' => 0, 'suspended' => 0], $result);
        Notification::assertNothingSent();
    }

    public function test_un_abonnement_a_jour_n_est_jamais_touche(): void
    {
        Notification::fake();
        $subscription = $this->subscription('active');

        app(ProcessOverdueSubscriptions::class)->handle();

        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_un_espace_suspendu_est_renvoye_vers_l_abonnement_depuis_le_back_office_des_evenements(): void
    {
        $this->subscription('suspended');

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertRedirect(route('tenants.billing.show', $this->tenant));
    }

    public function test_l_ecran_d_abonnement_reste_accessible_a_un_espace_suspendu(): void
    {
        $this->subscription('suspended');

        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertOk();
    }

    public function test_un_espace_a_jour_n_est_pas_redirige(): void
    {
        $this->subscription('active');

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertOk();
    }

    public function test_la_suspension_d_une_organisation_ne_touche_pas_les_autres(): void
    {
        $this->subscription('suspended');

        $otherOwner = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($otherOwner, 'Autre Association');

        $this->actingAs($otherOwner)
            ->get(route('tenants.events.index', $other))
            ->assertOk();
    }

    public function test_un_espace_suspendu_n_accepte_plus_d_inscriptions_publiques(): void
    {
        $this->subscription('suspended');

        $accepts = $this->tenant->fresh()->asCurrent(fn () => Event::factory()->open()->create(['table_count' => 5, 'seats_per_table' => 10])->acceptsRegistrations());

        $this->assertFalse($accepts);
    }

    public function test_un_espace_a_jour_accepte_les_inscriptions_publiques(): void
    {
        $this->subscription('active');

        $accepts = $this->tenant->fresh()->asCurrent(fn () => Event::factory()->open()->create(['table_count' => 5, 'seats_per_table' => 10])->acceptsRegistrations());

        $this->assertTrue($accepts);
    }
}
