<?php

namespace App\Actions\Registrations;

use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Data\RefundDecision;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\User;
use App\Notifications\Registrations\RegistrationCancelled;
use App\Support\GuestMessageQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Annulation d'une inscription par l'organisation, a n'importe quel stade y compris `Confirmed`
 * (README 2.1, `RegistrationStatus::Cancelled`). Distincte d'`Expired`/`ProofRejected` : ce n'est
 * pas un echec du parcours invite, c'est une decision deliberee, jamais purgee automatiquement.
 *
 * Le billet n'a aucune colonne a modifier pour etre voide : `App\Actions\Scan\ScanTicket` refuse
 * deja tout billet dont l'inscription n'est plus `Confirmed`.
 */
class CancelRegistration
{
    /**
     * Cancel the given registration, releasing its seat and table immediately.
     *
     * Une inscription validee a ete payee : l'annulation fixe alors le sort du paiement (README
     * 2.11), « a rembourser » faute de decision. Avant validation, ou pour un evenement gratuit,
     * rien n'a ete encaisse et une decision eventuelle est ignoree.
     *
     * Idempotent : une inscription deja annulee n'est pas reecrite, la premiere annulation fait
     * foi.
     *
     * @throws \DomainException des frais qui atteignent le montant paye
     */
    public function handle(Registration $registration, string $reason, User $actor, ?RefundDecision $refund = null): void
    {
        if ($registration->status === RegistrationStatus::Cancelled) {
            return;
        }

        $previousStatus = $registration->status;
        $wasOccupyingASeat = $previousStatus === RegistrationStatus::Confirmed
            || ($previousStatus === RegistrationStatus::Held && ! $registration->holdHasExpired());

        $refund = $registration->hasCollectedPayment() ? ($refund ?? RefundDecision::due()) : null;
        $refund?->assertFeeBelow($registration->amount_due);

        DB::transaction(function () use ($registration, $reason, $actor, $previousStatus, $refund) {
            $registration->update([
                'status' => RegistrationStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                ...($refund === null ? [] : [
                    ...$refund->attributes(),
                    'refund_recorded_at' => now(),
                    'refund_recorded_by_user_id' => $actor->id,
                ]),
            ]);

            RegistrationTableAssignment::where('registration_id', $registration->id)->delete();

            activity()
                ->performedOn($registration)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => ['status' => $previousStatus->value],
                    'attributes' => [
                        'status' => 'cancelled',
                        'reason' => $reason,
                        ...($refund === null ? [] : [
                            'amount_paid' => $registration->amount_due,
                            'refund_status' => $refund->status->value,
                            'refund_channel' => $refund->channel?->value,
                            'refunded_on' => $refund->refundedOn?->toDateString(),
                            'refund_fee' => $refund->fee,
                            'refund_reference' => $refund->reference,
                            'refund_kept_reason' => $refund->keptReason,
                            'net_refund' => $registration->netRefund(),
                        ]),
                    ],
                ])
                ->log('registrations.cancelled');
        });

        $this->notifyGuest($registration);

        // Meme raisonnement que `PurgeRegistrations` (README 2.3) : des qu'une place se libere
        // reellement, la liste d'attente avance tant qu'il reste du monde en attente et de la
        // place pour au moins un de plus.
        if ($wasOccupyingASeat) {
            while (app(PromoteNextWaitlistEntry::class)->handle($registration->event) !== null) {
                //
            }
        }
    }

    /**
     * Tell the guest, rather than letting them discover it when their ticket is refused.
     *
     * Soumis au quota d'envois du plan (SECURITY.md H5) comme les autres messages a l'invite.
     */
    private function notifyGuest(Registration $registration): void
    {
        if (! GuestMessageQuota::allows()) {
            return;
        }

        Notification::route('whatsapp', $registration->phone)
            ->route('mail', $registration->email)
            ->notify(new RegistrationCancelled($registration));

        GuestMessageQuota::record();
    }
}
