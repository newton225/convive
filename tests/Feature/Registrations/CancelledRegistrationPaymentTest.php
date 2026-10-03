<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\CancelRegistration;
use App\Actions\Registrations\RecordRefund;
use App\Actions\Tenants\CreateTenant;
use App\Data\RefundDecision;
use App\Enums\PaymentChannel;
use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\RefundSent;
use App\Notifications\Registrations\RegistrationCancelled;
use App\Support\Money;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * README 2.11 : le sort du paiement d'une inscription annulee. L'application ne rembourse
 * jamais elle-meme, elle garde la trace de ce que devient l'argent.
 */
class CancelledRegistrationPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user): Tenant
    {
        return app(CreateTenant::class)->handle($user, 'Association Convive');
    }

    private function confirmedRegistration(Tenant $tenant, Event $event, int $amountDue = 60000): Registration
    {
        return $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'amount_due' => $amountDue,
        ]));
    }

    public function test_une_inscription_validee_annulee_sans_choix_est_a_rembourser(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $this->assertSame(RefundStatus::Due, $tenant->asCurrent(fn () => $registration->fresh())->refund_status);
    }

    public function test_une_inscription_sans_paiement_valide_n_a_aucun_paiement_a_traiter(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]));

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle(
            $registration,
            'Motif.',
            $owner,
            RefundDecision::kept('Ignore : rien n\'a ete encaisse.'),
        ));

        $fresh = $tenant->asCurrent(fn () => $registration->fresh());
        $this->assertNull($fresh->refund_status);
        $this->assertNull($fresh->refund_kept_reason);
    }

    public function test_une_inscription_gratuite_validee_n_a_rien_a_rembourser(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event, 0);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $this->assertNull($tenant->asCurrent(fn () => $registration->fresh())->refund_status);
    }

    public function test_un_remboursement_deja_effectue_garde_moyen_date_reference_et_frais(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle(
            $registration,
            'Motif.',
            $owner,
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-28'), 600, 'WAVE-123'),
        ));

        $fresh = $tenant->asCurrent(fn () => $registration->fresh());
        $this->assertSame(RefundStatus::Refunded, $fresh->refund_status);
        $this->assertSame(PaymentChannel::Wave, $fresh->refund_channel);
        $this->assertSame('2026-09-28', $fresh->refunded_on->toDateString());
        $this->assertSame('WAVE-123', $fresh->refund_reference);
        $this->assertSame(600, $fresh->refund_fee);
    }

    public function test_le_montant_rembourse_a_l_invite_est_le_montant_paye_moins_les_frais(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event, 60000);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle(
            $registration,
            'Motif.',
            $owner,
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-28'), 600),
        ));

        $this->assertSame(59400, $tenant->asCurrent(fn () => $registration->fresh())->netRefund());
    }

    public function test_des_frais_egaux_ou_superieurs_au_montant_paye_sont_refuses(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event, 20000);

        $this->expectException(DomainException::class);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle(
            $registration,
            'Motif.',
            $owner,
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-28'), 20000),
        ));
    }

    public function test_un_paiement_conserve_garde_son_motif(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle(
            $registration,
            'Motif.',
            $owner,
            RefundDecision::kept('Annulation hors delai.'),
        ));

        $fresh = $tenant->asCurrent(fn () => $registration->fresh());
        $this->assertSame(RefundStatus::Kept, $fresh->refund_status);
        $this->assertSame('Annulation hors delai.', $fresh->refund_kept_reason);
    }

    public function test_marquer_comme_rembourse_fait_passer_une_annulation_a_rembourser_a_remboursee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);
        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $tenant->asCurrent(fn () => app(RecordRefund::class)->handle(
            $registration->fresh(),
            RefundDecision::refunded(PaymentChannel::OrangeMoney, Carbon::parse('2026-09-29'), 500),
            $owner,
        ));

        $fresh = $tenant->asCurrent(fn () => $registration->fresh());
        $this->assertSame(RefundStatus::Refunded, $fresh->refund_status);
        $this->assertSame(PaymentChannel::OrangeMoney, $fresh->refund_channel);
        $this->assertSame(500, $fresh->refund_fee);
    }

    public function test_seule_une_annulation_a_rembourser_peut_etre_marquee_remboursee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);
        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle(
            $registration,
            'Motif.',
            $owner,
            RefundDecision::kept('Don a l\'association.'),
        ));

        $this->expectException(DomainException::class);

        $tenant->asCurrent(fn () => app(RecordRefund::class)->handle(
            $registration->fresh(),
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-29'), 0),
            $owner,
        ));
    }

    public function test_une_inscription_non_annulee_ne_peut_pas_etre_marquee_remboursee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $this->expectException(DomainException::class);

        $tenant->asCurrent(fn () => app(RecordRefund::class)->handle(
            $registration,
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-29'), 0),
            $owner,
        ));
    }

    public function test_le_remboursement_est_journalise_avec_l_auteur_le_montant_et_les_frais(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event, 60000);
        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $tenant->asCurrent(fn () => app(RecordRefund::class)->handle(
            $registration->fresh(),
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-29'), 600),
            $owner,
        ));

        $activity = $tenant->asCurrent(
            fn () => Activity::where('description', 'registrations.refunded')->latest('id')->first(),
        );

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame(59400, $activity->properties['attributes']['net_refund']);
        $this->assertSame(600, $activity->properties['attributes']['refund_fee']);
    }

    public function test_le_sort_du_paiement_est_journalise_avec_l_annulation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle(
            $registration,
            'Motif.',
            $owner,
            RefundDecision::kept('Annulation hors delai.'),
        ));

        $activity = $tenant->asCurrent(
            fn () => Activity::where('description', 'registrations.cancelled')->latest('id')->first(),
        );

        $this->assertSame('kept', $activity->properties['attributes']['refund_status']);
        $this->assertSame('Annulation hors delai.', $activity->properties['attributes']['refund_kept_reason']);
    }

    public function test_les_totaux_distinguent_encaisse_rembourse_frais_a_rembourser_et_net(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->confirmedRegistration($tenant, $event, 20000);
        $refunded = $this->confirmedRegistration($tenant, $event, 60000);
        $due = $this->confirmedRegistration($tenant, $event, 40000);
        $kept = $this->confirmedRegistration($tenant, $event, 10000);

        $tenant->asCurrent(function () use ($refunded, $due, $kept, $owner) {
            app(CancelRegistration::class)->handle($refunded, 'Motif.', $owner, RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-28'), 600));
            app(CancelRegistration::class)->handle($due, 'Motif.', $owner);
            app(CancelRegistration::class)->handle($kept, 'Motif.', $owner, RefundDecision::kept('Don.'));
        });

        $totals = $tenant->asCurrent(fn () => $event->fresh()->paymentTotals());

        // Encaisse : toutes les preuves validees, y compris celles des inscriptions annulees.
        $this->assertSame(130000, $totals['collected']);
        // Rembourse : tout ce qui est sorti du compte pour l'inscription, frais compris.
        $this->assertSame(60000, $totals['refunded']);
        $this->assertSame(600, $totals['fees']);
        $this->assertSame(40000, $totals['due']);
        $this->assertSame(70000, $totals['net']);
    }

    public function test_l_invite_est_prevenu_de_l_annulation(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        Notification::assertSentOnDemand(RegistrationCancelled::class);
    }

    public function test_l_invite_est_prevenu_quand_le_remboursement_est_effectue(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);
        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $tenant->asCurrent(fn () => app(RecordRefund::class)->handle(
            $registration->fresh(),
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-29'), 600),
            $owner,
        ));

        Notification::assertSentOnDemand(RefundSent::class);
    }

    public function test_le_modele_whatsapp_d_annulation_dit_ce_que_devient_le_paiement(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        Notification::assertSentOnDemand(RegistrationCancelled::class, fn (RegistrationCancelled $notification) => $notification->whatsAppTemplate(null)->parameters[3]
            === __('guest.refund.due', ['amount' => Money::format(60000)]));
    }

    public function test_le_modele_whatsapp_d_annulation_sans_paiement_ne_laisse_aucune_variable_vide(): void
    {
        // Meta refuse un modele dont une variable est vide : la phrase sur le paiement en a une a
        // dire meme quand rien n'a ete encaisse.
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event, 0);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        Notification::assertSentOnDemand(RegistrationCancelled::class, fn (RegistrationCancelled $notification) => $notification->whatsAppTemplate(null)->parameters[3]
            === __('guest.refund.none'));
    }

    public function test_le_motif_d_annulation_sur_plusieurs_lignes_tient_sur_une_seule_dans_le_modele(): void
    {
        // Meta refuse une variable qui porte un retour a la ligne, une tabulation ou plus de quatre
        // espaces de suite : le motif saisi dans une zone de texte en porte souvent.
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, "Salle annulee.\r\n\r\nNous\tsommes      desoles.", $owner));

        Notification::assertSentOnDemand(RegistrationCancelled::class, fn (RegistrationCancelled $notification) => $notification->whatsAppTemplate(null)->parameters[2]
            === 'Salle annulee. Nous sommes desoles.');
    }

    public function test_le_modele_whatsapp_du_remboursement_donne_montant_date_moyen_et_frais(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $this->confirmedRegistration($tenant, $event);
        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $tenant->asCurrent(fn () => app(RecordRefund::class)->handle(
            $registration->fresh(),
            RefundDecision::refunded(PaymentChannel::Wave, Carbon::parse('2026-09-29'), 600),
            $owner,
        ));

        Notification::assertSentOnDemand(RefundSent::class, fn (RefundSent $notification) => array_slice($notification->whatsAppTemplate(null)->parameters, 2) === [
            Money::format(59400),
            Carbon::parse('2026-09-29')->isoFormat('LL'),
            PaymentChannel::Wave->label(),
            Money::format(600),
        ]);
    }
}
