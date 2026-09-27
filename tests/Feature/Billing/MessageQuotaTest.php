<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\SendInvitationCard;
use App\Enums\NotificationType;
use App\Enums\PlanCode;
use App\Models\Event;
use App\Models\MessageUsage;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Quota d'envois par plan (SECURITY.md H5, decision du 2026-09-27) : aucun plafond aujourd'hui,
 * mais la mecanique est prete. Chaque message envoye a un invite est compte au mois ; un plan qui
 * recoit un plafond suspend les envois au-dela et previent les responsables de l'abonnement.
 */
class MessageQuotaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.billing.enforce_plan_limits' => true]);

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function confirmedRegistration(): Registration
    {
        return $this->tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();

            return Registration::factory()->confirmed()->create(['event_id' => $event->id]);
        });
    }

    private function sendCard(Registration $registration): bool
    {
        return $this->tenant->asCurrent(fn () => app(SendInvitationCard::class)->handle($registration));
    }

    public function test_sans_plafond_les_envois_sont_illimites_et_comptes(): void
    {
        Notification::fake();

        $this->assertNull(PlanCode::Essential->definition()['max_messages_per_month']);

        $this->assertTrue($this->sendCard($this->confirmedRegistration()));
        $this->assertTrue($this->sendCard($this->confirmedRegistration()));

        $this->assertSame(2, PlanLimits::for($this->tenant)->messagesThisMonth());
        $this->assertSame(['used' => 2, 'max' => null], PlanLimits::for($this->tenant)->usage()['messages']);
    }

    public function test_au_dela_du_plafond_l_envoi_est_suspendu_et_l_abonnement_prevenu_une_fois(): void
    {
        Notification::fake();
        Plan::ensure(PlanCode::Essential)->update(['max_messages_per_month' => 1]);

        $this->assertTrue($this->sendCard($this->confirmedRegistration()));

        $blocked = $this->confirmedRegistration();
        $this->assertFalse($this->sendCard($blocked));
        $this->assertFalse($this->sendCard($this->confirmedRegistration()));

        $this->assertNull($this->tenant->asCurrent(fn () => $blocked->fresh()->card_sent_at));
        $this->assertSame(1, Notification::sent($this->owner, TenantAlert::class)
            ->filter(fn (TenantAlert $alert) => $alert->type === NotificationType::MessageQuotaReached)
            ->count());
    }

    public function test_le_compteur_repart_chaque_mois(): void
    {
        Notification::fake();
        Plan::ensure(PlanCode::Essential)->update(['max_messages_per_month' => 1]);

        $this->assertTrue($this->sendCard($this->confirmedRegistration()));

        $this->travel(1)->months();

        $this->assertTrue($this->sendCard($this->confirmedRegistration()));
        $this->assertSame(2, $this->tenant->asCurrent(fn () => MessageUsage::count()));
    }
}
