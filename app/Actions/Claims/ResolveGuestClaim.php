<?php

namespace App\Actions\Claims;

use App\Enums\ClaimStatus;
use App\Models\GuestClaim;
use App\Models\User;

/**
 * Marque une reclamation comme traitee (decision du 2026-10-09). Idempotent : une reclamation deja
 * traitee ne change ni de date ni d'auteur, un double clic ne reecrit rien.
 */
class ResolveGuestClaim
{
    public function handle(GuestClaim $claim, User $actor): GuestClaim
    {
        if ($claim->status === ClaimStatus::Resolved) {
            return $claim;
        }

        $claim->update([
            'status' => ClaimStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by_user_id' => $actor->id,
        ]);

        activity()
            ->performedOn($claim->registration)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'old' => ['claim_status' => ClaimStatus::Open->value],
                'attributes' => ['claim_status' => ClaimStatus::Resolved->value, 'claim_category' => $claim->category->value],
            ])
            ->log('registrations.claim_resolved');

        return $claim;
    }
}
