<?php

namespace App\Actions\PaymentProofs;

use App\Actions\Notifications\SendAlert;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Models\PaymentProof;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rejet d'une preuve (README ecran 18, cycle de vie 2.1), etape 6 de « Ordre de construction ».
 *
 * `ProofRejected` figure deja parmi les statuts non finalises purgeables
 * (`Registration::UnfinalizedStatuses`) : le retour a `Held` n'est pas immediat, il passe par
 * le meme mecanisme de relance que l'expiration (`Public\RegistrationController::retry()`),
 * qui revérifie le stock avant de redemarrer le decompte (README 2.2). Pas de nouveau delai a
 * inventer ici.
 */
class RejectPaymentProof
{
    /**
     * Attempt to reject the given proof.
     *
     * @return bool false quand la preuve n'est plus en attente de verification (deja validee,
     *              ou deja rejetee par ailleurs) : l'appelant decide de la reponse.
     */
    public function handle(PaymentProof $proof, User $actor): bool
    {
        $registration = $proof->registration;

        // Idempotent par construction (SECURITY.md H4), meme raisonnement que
        // `ValidatePaymentProof` : la garde sur le statut stocke tient lieu de cle
        // d'idempotence pour une transition qui ne cree aucun enregistrement.
        if ($registration->status === RegistrationStatus::ProofRejected) {
            return true;
        }

        if ($registration->status !== RegistrationStatus::ProofSubmitted) {
            return false;
        }

        DB::transaction(function () use ($registration, $proof, $actor) {
            $registration->update(['status' => RegistrationStatus::ProofRejected]);

            activity()
                ->performedOn($proof)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => ['status' => 'proof_submitted'],
                    'attributes' => ['status' => 'proof_rejected'],
                ])
                ->log('proofs.rejected');
        });

        app(SendAlert::class)->toTenantMembers(
            NotificationType::ProofRejected,
            ['name' => $registration->name, 'event' => $registration->event->name],
            route('tenants.events.proofs.index', [Tenant::current(), $registration->event], absolute: false),
            except: $actor,
        );

        return true;
    }
}
