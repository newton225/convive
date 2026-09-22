<?php

namespace App\Actions\Registrations;

use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Annulation d'une inscription par l'organisation, a n'importe quel stade y compris `Confirmed`
 * (etape 9, decision produit documentee sur `RegistrationStatus::Cancelled`). Distincte
 * d'`Expired`/`ProofRejected` : ce n'est pas un echec du parcours invite, c'est une decision
 * deliberee, jamais purgee automatiquement.
 *
 * Le billet n'a aucune colonne a modifier pour etre voide : `App\Actions\Scan\ScanTicket` refuse
 * deja tout billet dont l'inscription n'est plus `Confirmed`.
 */
class CancelRegistration
{
    /**
     * Cancel the given registration, releasing its seat and table immediately.
     *
     * Idempotent : une inscription deja annulee n'est pas reecrite, la premiere annulation fait
     * foi.
     */
    public function handle(Registration $registration, string $reason, User $actor): void
    {
        if ($registration->status === RegistrationStatus::Cancelled) {
            return;
        }

        $previousStatus = $registration->status;
        $wasOccupyingASeat = $previousStatus === RegistrationStatus::Confirmed
            || ($previousStatus === RegistrationStatus::Held && ! $registration->holdHasExpired());

        DB::transaction(function () use ($registration, $reason, $actor, $previousStatus) {
            $registration->update([
                'status' => RegistrationStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
            ]);

            RegistrationTableAssignment::where('registration_id', $registration->id)->delete();

            activity()
                ->performedOn($registration)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => ['status' => $previousStatus->value],
                    'attributes' => ['status' => 'cancelled', 'reason' => $reason],
                ])
                ->log('registrations.cancelled');
        });

        // Meme raisonnement que `PurgeRegistrations` (README 2.3) : des qu'une place se libere
        // reellement, la liste d'attente avance tant qu'il reste du monde en attente et de la
        // place pour au moins un de plus.
        if ($wasOccupyingASeat) {
            while (app(PromoteNextWaitlistEntry::class)->handle($registration->event) !== null) {
                //
            }
        }
    }
}
