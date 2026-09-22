<?php

namespace App\Actions\Reconciliation;

use App\Enums\ReconciliationOutcome;
use App\Models\Registration;
use App\Models\StatementLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolution manuelle d'une ligne de releve (README 2.10, ecran 19), etape 9 de « Ordre de
 * construction ».
 *
 * Avec une inscription : la ligne lui est rattachee (et a sa derniere preuve, si elle en porte
 * une) et passe en « rapprochee ». Sans inscription : la ligne est marquee vue et son eventuelle
 * proposition automatique est retiree, elle repasse en « sans inscription ». `resolved_at`
 * distingue ensuite « pas encore regardee » de « vue, sans correspondance ».
 */
class ResolveStatementLine
{
    /**
     * Resolve the line, matching it to the given registration or marking it as seen without one.
     *
     * @throws ValidationException Quand l'inscription n'appartient pas a l'evenement du releve.
     */
    public function handle(StatementLine $line, ?Registration $registration, User $actor): StatementLine
    {
        if ($registration !== null && $registration->event_id !== $line->statementImport->event_id) {
            throw ValidationException::withMessages([
                'registration_id' => __('reconciliation.errors.registration_other_event'),
            ]);
        }

        $previousOutcome = $line->outcome;

        DB::transaction(function () use ($line, $registration, $actor, $previousOutcome) {
            $line->update([
                'outcome' => $registration !== null ? ReconciliationOutcome::Matched : ReconciliationOutcome::NoRegistration,
                'matched_registration_id' => $registration?->id,
                'matched_payment_proof_id' => $registration?->latestProof?->id,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ]);

            activity()
                ->performedOn($line)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => ['outcome' => $previousOutcome->value],
                    'attributes' => [
                        'outcome' => $line->outcome->value,
                        'registration_id' => $registration?->id,
                    ],
                ])
                ->log('reconciliation.resolved');
        });

        return $line;
    }
}
