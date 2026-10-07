<?php

namespace App\Actions\PaymentProofs;

use App\Actions\Registrations\FinalizeConfirmedRegistration;
use App\Enums\RegistrationStatus;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Validation d'une preuve (README ecran 18), etape 6 de « Ordre de construction ».
 *
 * L'attribution automatique des tables (README 2.6) et l'emission du billet (README 2.8, etape
 * 7) suivent immediatement la confirmation, dans l'ordre de validation : chaque appel traite une
 * preuve a la fois, l'ordre d'appel est deja l'ordre de validation. Hors de la transaction qui
 * confirme : `AssignTable` et `IssueTicket` gerent chacune leur propre atomicite (verrou par
 * evenement ou par inscription), et une inscription confirmee sans table assise reste un etat
 * valide, pas une erreur, en attendant un placement manuel.
 *
 * La carte d'invitation (README 2.7, etape 8) suit la meme logique si l'echeance programmee de
 * l'evenement est deja passee : « une inscription validee apres l'echeance est envoyee a la
 * validation ». Sinon, c'est la tache planifiee qui la servira au bon moment.
 */
class ValidatePaymentProof
{
    /**
     * Attempt to validate the given proof.
     *
     * @return bool false quand la preuve n'est plus en attente de verification (deja rejetee,
     *              ou l'inscription a expire entre-temps) : l'appelant decide de la reponse.
     */
    public function handle(PaymentProof $proof, User $actor): bool
    {
        $registration = $proof->registration;

        // Idempotent par construction (SECURITY.md H4) : une inscription deja confirmee ne
        // l'est pas une seconde fois, qu'il s'agisse d'un double clic ou d'un rejeu reseau. Pas
        // de cle d'idempotence a part : la garde sur le statut stocke joue le meme role, sans
        // colonne supplementaire, pour une transition qui ne cree aucun enregistrement.
        // L'attribution de table, l'emission du billet et l'envoi de la carte sont retentes
        // quand meme : les trois actions sont elles-memes idempotentes, et un appel precedent a
        // pu confirmer sans reussir a asseoir (aucune table libre alors) ou sans que l'echeance
        // programmee ne soit encore passee.
        if ($registration->status === RegistrationStatus::Confirmed) {
            app(FinalizeConfirmedRegistration::class)->handle($registration);

            return true;
        }

        if ($registration->status !== RegistrationStatus::ProofSubmitted) {
            return false;
        }

        DB::transaction(function () use ($registration, $proof, $actor) {
            $registration->update(['status' => RegistrationStatus::Confirmed]);

            activity()
                ->performedOn($proof)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => ['status' => 'proof_submitted'],
                    'attributes' => ['status' => 'confirmed'],
                ])
                ->log('proofs.validated');
        });

        app(FinalizeConfirmedRegistration::class)->handle($registration);

        return true;
    }
}
