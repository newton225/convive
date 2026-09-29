<?php

namespace App\Actions\Registrations;

use App\Data\RefundDecision;
use App\Enums\RefundStatus;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\Registrations\RefundSent;
use App\Support\GuestMessageQuota;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Marquer comme rembourse une annulation « a rembourser », une fois l'argent parti (README
 * 2.11). Seul changement possible apres coup sur le sort d'un paiement : un paiement deja
 * rembourse ou conserve ne se rejoue pas, et l'inscription reste annulee.
 */
class RecordRefund
{
    /**
     * @throws DomainException une inscription qui n'est pas une annulation a rembourser, une
     *                         decision qui n'est pas un remboursement, ou des frais qui
     *                         atteignent le montant paye
     */
    public function handle(Registration $registration, RefundDecision $refund, User $actor): void
    {
        if ($registration->status !== RegistrationStatus::Cancelled || $registration->refund_status !== RefundStatus::Due) {
            throw new DomainException('Seule une annulation a rembourser peut etre marquee remboursee.');
        }

        if ($refund->status !== RefundStatus::Refunded) {
            throw new DomainException('Marquer comme rembourse exige un remboursement.');
        }

        $refund->assertFeeBelow($registration->amount_due);

        DB::transaction(function () use ($registration, $refund, $actor) {
            $registration->update([
                ...$refund->attributes(),
                'refund_recorded_at' => now(),
                'refund_recorded_by_user_id' => $actor->id,
            ]);

            activity()
                ->performedOn($registration)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => ['refund_status' => RefundStatus::Due->value],
                    'attributes' => [
                        'refund_status' => RefundStatus::Refunded->value,
                        'amount_paid' => $registration->amount_due,
                        'refund_channel' => $refund->channel?->value,
                        'refunded_on' => $refund->refundedOn?->toDateString(),
                        'refund_fee' => $refund->fee,
                        'refund_reference' => $refund->reference,
                        'net_refund' => $registration->netRefund(),
                    ],
                ])
                ->log('registrations.refunded');
        });

        // Soumis au quota d'envois du plan (SECURITY.md H5), comme les autres messages a l'invite.
        if (GuestMessageQuota::allows()) {
            Notification::route('whatsapp', $registration->phone)
                ->route('mail', $registration->email)
                ->notify(new RefundSent($registration));

            GuestMessageQuota::record();
        }
    }
}
